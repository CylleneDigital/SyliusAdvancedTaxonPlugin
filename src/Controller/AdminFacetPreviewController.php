<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Controller;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonQueryBuilder;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
final readonly class AdminFacetPreviewController
{
    /**
     * CSRF token id expected in the "X-CSRF-Token" header of a preview request.
     */
    private const string CSRF_TOKEN_ID = 'cyllene_digital_sylius_advanced_taxon_facet_preview';

    private const int MAX_CONDITIONS = 32;

    private const int MAX_PRODUCTS = 20;

    public function __construct(
        private ConditionalTaxonQueryBuilder $conditionalTaxonQueryBuilder,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    private static function stringField(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? mb_substr($value, 0, 255) : null;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $submittedToken = (string) $request->headers->get('X-CSRF-Token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $submittedToken))) {
            return new JsonResponse(['error' => 'invalid_csrf_token'], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $submittedConditions = $payload['conditions'] ?? [];
        if (!is_array($submittedConditions)) {
            $submittedConditions = [];
        }

        // The payload comes from the browser: only lists of string fields reach the query builder.
        $conditions = [];
        foreach (array_slice(array_values(array_filter($submittedConditions, 'is_array')), 0, self::MAX_CONDITIONS) as $condition) {
            $conditions[] = [
                'conditionType' => self::stringField($condition, 'conditionType') ?? '',
                'operator' => self::stringField($condition, 'operator') ?? '',
                'referenceCode' => self::stringField($condition, 'referenceCode'),
                'value' => self::stringField($condition, 'value'),
            ];
        }

        // The synchronization reads names and descriptions in the default locale: the preview too.
        $locale = $this->translationLocaleProvider->getDefaultLocaleCode();

        $result = $this->conditionalTaxonQueryBuilder->previewConditions($conditions, $locale, self::MAX_PRODUCTS);

        $products = array_map(
            fn (array $product): array => $product + ['url' => $this->urlGenerator->generate('sylius_admin_product_show', ['id' => $product['id']])],
            $result['products'],
        );

        return new JsonResponse([
            'count' => $result['count'],
            'products' => $products,
            'limit' => self::MAX_PRODUCTS,
        ]);
    }
}
