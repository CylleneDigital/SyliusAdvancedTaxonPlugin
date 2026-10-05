<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType as AdminTaxonType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Icons\Exception\IconNotFoundException;
use Symfony\UX\Icons\IconRendererInterface;

/**
 * Color, icon or uploaded pictogram of a taxon, and where they are shown.
 */
final class TaxonAppearanceTypeExtension extends AbstractTypeExtension
{
    /**
     * Icon of each taxon as loaded, before the submission: the submitted icon field is a plain text
     * input, it never decides which stored pictogram may be removed.
     *
     * @var \WeakMap<AdvancedTaxonInterface, ?string>
     */
    private \WeakMap $loadedIcons;

    public function __construct(
        private readonly TaxonIconUploader $taxonIconUploader,
        private readonly TranslatorInterface $translator,
        private readonly IconRendererInterface $iconRenderer,
    ) {
        $this->loadedIcons = new \WeakMap();
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $taxon = $builder->getData();

        $builder
            // A color input cannot be empty: browsers submit #000000 for it. Whether the taxon has a
            // color at all is therefore a separate checkbox.
            ->add('hasColor', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $taxon instanceof AdvancedTaxonInterface && $taxon->getColor() !== null,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.has_color',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.has_color_help',
            ])
            ->add('color', ColorType::class, [
                'required' => false,
                'html5' => true,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.color',
            ])
            ->add('icon', TextType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.icon',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.icon_help',
            ])
            ->add('iconFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.icon_file',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.icon_file_help',
                'attr' => ['accept' => 'image/*'],
            ])
            ->add('iconType', ChoiceType::class, [
                'choices' => [
                    'cyllene_digital_sylius_advanced_taxon.form.icon_type_choices.icon' => 'icon',
                    'cyllene_digital_sylius_advanced_taxon.form.icon_type_choices.image' => 'image',
                ],
                'required' => true,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.icon_type',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.icon_type_help',
            ])
            ->add('showCustomizationInMenu', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_menu',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_menu_help',
            ])
            ->add('showCustomizationOnTaxonPage', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_on_taxon_page',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_on_taxon_page_help',
            ])
            ->add('showCustomizationInBreadcrumbs', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_breadcrumbs',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_breadcrumbs_help',
            ])
        ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $taxon = $event->getData();
            if ($taxon instanceof AdvancedTaxonInterface) {
                $this->loadedIcons[$taxon] = $taxon->getIcon();
            }
        });

        // Runs after the validation listener: the pictogram is only stored for a valid submission.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $taxon = $event->getData();
            $iconFile = $form->get('iconFile')->getData();

            if (!$taxon instanceof AdvancedTaxonInterface || !$form->getRoot()->isValid()) {
                return;
            }

            $loadedIcon = $this->loadedIcons[$taxon] ?? null;

            if ($iconFile instanceof UploadedFile && $form->get('iconType')->getData() === 'image') {
                $icon = $this->taxonIconUploader->upload($iconFile);
                if ($icon === null) {
                    $form->get('iconFile')->addError(new FormError(
                        $this->translator->trans('cyllene_digital_sylius_advanced_taxon.icon.invalid_file', [], 'validators'),
                    ));

                    return;
                }

                $taxon->setIcon($icon);
            } elseif (TaxonIconUploader::storagePath($taxon->getIcon()) !== null && $taxon->getIcon() !== $loadedIcon) {
                // An uploaded pictogram only comes from an upload, never from the text field.
                $taxon->setIcon($loadedIcon);
            } elseif ($taxon->getIcon() !== null && $taxon->getIcon() !== $loadedIcon && !$this->canRender($taxon->getIcon())) {
                // The shop silently renders nothing for an icon it cannot find.
                $form->get('icon')->addError(new FormError(
                    $this->translator->trans('cyllene_digital_sylius_advanced_taxon.icon.not_found', ['%icon%' => $taxon->getIcon()], 'validators'),
                ));
            }
        }, -100);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $taxon = $event->getData();
            if ($taxon instanceof AdvancedTaxonInterface && $event->getForm()->get('hasColor')->getData() !== true) {
                $taxon->setColor(null);
            }
        });
    }

    private function canRender(string $icon): bool
    {
        try {
            $this->iconRenderer->renderIcon(str_contains($icon, ':') ? $icon : 'tabler:' . $icon);
        } catch (IconNotFoundException) {
            return false;
        }

        return true;
    }

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [AdminTaxonType::class];
    }
}
