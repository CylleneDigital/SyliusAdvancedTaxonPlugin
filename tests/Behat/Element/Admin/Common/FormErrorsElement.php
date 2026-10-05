<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Common;

use FriendsOfBehat\PageObjectExtension\Element\Element;

/**
 * Validation messages rendered by an admin form after a rejected submission.
 */
final class FormErrorsElement extends Element
{
    public function hasError(string $message): bool
    {
        foreach ($this->getDocument()->findAll('css', '.invalid-feedback, .form-error-message, .alert-danger') as $error) {
            if (str_contains($error->getText(), $message)) {
                return true;
            }
        }

        return false;
    }
}
