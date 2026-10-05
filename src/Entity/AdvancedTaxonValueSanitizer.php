<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

/**
 * Sanitizes the free-form values rendered on the storefront (icon references and media links).
 */
final class AdvancedTaxonValueSanitizer
{
    /**
     * A taxon icon is either a pictogram uploaded by the plugin ("image:" followed by its path in
     * the Sylius image storage), or a glyph name from one of the configured icon libraries.
     *
     * Everything else (a local path, a remote URL, a protocol relative path, "javascript:", a path
     * climbing out of the storage, ...) is rejected so it can never be rendered as an image source
     * or used for an icon lookup.
     */
    public static function sanitizeIcon(?string $icon): ?string
    {
        if ($icon === null) {
            return null;
        }

        $icon = trim($icon);

        if ($icon === '') {
            return null;
        }

        if (str_starts_with($icon, AdvancedTaxonInterface::ICON_IMAGE_PREFIX)) {
            $path = substr($icon, strlen(AdvancedTaxonInterface::ICON_IMAGE_PREFIX));

            return preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*\.[a-z0-9]+$#i', $path) === 1 ? $icon : null;
        }

        return preg_match('#^[a-z0-9][a-z0-9:_-]*$#i', $icon) === 1 ? $icon : null;
    }

    /**
     * The color is rendered inside "style" attributes on the storefront: only a "#rrggbb" value is
     * kept, anything else could inject CSS.
     */
    public static function sanitizeColor(?string $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? strtolower($color) : null;
    }

    /**
     * Returns the destination URL only when it is safe to use as a link target.
     *
     * Only http(s) URLs and same-origin relative paths are accepted. Anything else
     * ("javascript:", "data:", protocol relative "//host", ...) is dropped, since the value is
     * rendered inside an "href" attribute on the storefront and would otherwise be a stored XSS
     * vector.
     */
    public static function sanitizeDestinationUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        // Browsers drop tabs and line breaks from a URL: "/\t/host" would become "//host".
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        // "//host" and "/\host" are both read by browsers as a link to another host.
        if (preg_match('#^/(?![/\\\\])#', $url) === 1 && !str_contains($url, '\\')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
