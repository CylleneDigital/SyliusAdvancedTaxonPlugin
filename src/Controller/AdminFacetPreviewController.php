<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Controller;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetProductQueryBuilder;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
final class AdminFacetPreviewController
{
    /**
     * CSRF token id expected in the "X-CSRF-Token" header of a preview request.
     */
    private const CSRF_TOKEN_ID = 'cyllene_digital_sylius_advanced_taxon_facet_preview';

    /**
     * Maximum number of conditions accepted in a single preview request.
     */
    private const MAX_CONDITIONS = 32;

    /**
     * Maximum number of products returned by a preview request.
     */
    private const MAX_PRODUCTS = 20;

    public function __construct(
        private readonly FacetProductQueryBuilder $facetProductQueryBuilder,
        private readonly LocaleContextInterface $localeContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
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

        $conditions = $payload['conditions'] ?? [];
        if (!is_array($conditions)) {
            $conditions = [];
        }

        /** @var array<int, array{conditionType?: string, operator?: string, referenceCode?: string|null, value?: string|null}> $conditions */
        $conditions = array_slice($conditions, 0, self::MAX_CONDITIONS);

        $locale = $payload['locale'] ?? $this->localeContext->getLocaleCode();
        if (!is_string($locale) || $locale === '') {
            $locale = $this->localeContext->getLocaleCode();
        }

        $result = $this->facetProductQueryBuilder->previewConditions($conditions, $locale, self::MAX_PRODUCTS);

        $products = [];
        foreach ($result['products'] as $product) {
            if (!$product instanceof ProductInterface) {
                continue;
            }

            $products[] = [
                'id' => $product->getId(),
                'code' => $product->getCode(),
                'name' => $product->getName(),
            ];
        }

        return new JsonResponse([
            'count' => $result['count'],
            'products' => $products,
            'limit' => self::MAX_PRODUCTS,
        ]);
    }
}
