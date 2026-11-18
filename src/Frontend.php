<?php

/**
 * @brief YAGB, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\YAGB;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Throwable;

/**
 * Frontend: load the grid CSS and the lightbox, only on HTML pages that
 * contain a gallery (and only if the setting is on). They are added to the
 * rendered post content, so page caches store them with the page.
 */
class Frontend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (!My::config()['front_assets']) {
            return true;
        }

        // Main path: add the assets to the post content as it is rendered, so they are part
        // of the page that page-cache plugins (e.g. Static Cache) store and serve later.
        App::behavior()->addBehavior('publicAfterContentFilterV2', self::contentFilter(...));

        // Fallback for galleries outside post content (widgets, templates)
        App::behavior()->addBehavior('publicPrependV2', function (): string {
            App::behavior()->addBehavior('urlHandlerServeDocument', self::serveDocument(...));

            return '';
        });

        return true;
    }

    /** Assets already added to this page. */
    private static bool $added = false;

    /**
     * Post content/excerpt filter: before the first gallery of the page, add the CSS
     * and the lightbox script (a <style> element in the body is valid HTML).
     *
     * @param   array<int|string, mixed>    $args   $args[0] is a reference to the content
     */
    public static function contentFilter(string $tag, array $args): string
    {
        try {
            if (self::$added || !in_array($tag, ['EntryContent', 'EntryExcerpt'], true) || !isset($args[0]) || !is_string($args[0])) {
                return '';
            }

            // Text outputs (feeds, meta descriptions, cut excerpts…) are left alone
            foreach (['encode_xml', 'encode_html', 'strip_tags', 'remove_html', 'cut_string'] as $filter) {
                if (!empty($args[$filter])) {
                    return '';
                }
            }
            if (in_array(App::url()->getType(), ['feed', 'trackback', 'xmlrpc', 'rsd'], true)) {
                return '';
            }

            $class = My::config()['css_class'];
            if (!self::hasGallery($args[0], $class)) {
                return '';
            }

            self::$added = true;
            $args[0]     = self::style($class) . self::script($class) . $args[0];
        } catch (Throwable) {
        }

        return '';
    }

    /**
     * @param   ArrayObject<string, mixed>  $result
     */
    public static function serveDocument(ArrayObject $result): string
    {
        try {
            $type = isset($result['content_type']) && is_string($result['content_type']) ? $result['content_type'] : '';
            if (!isset($result['content']) || !is_string($result['content']) || !str_starts_with(strtolower($type), 'text/html')) {
                return '';
            }

            $result['content'] = self::addAssets($result['content'], My::config()['css_class']);
        } catch (Throwable) {
        }

        return '';
    }

    public static function addAssets(string $html, string $class): string
    {
        // Already added by the content filter, or no gallery on the page
        if (str_contains($html, 'id="yagb-css"') || !self::hasGallery($html, $class)) {
            return $html;
        }

        $html = (int) stripos($html, '</head>') > 0 ? (string) preg_replace('#</head>#i', self::style($class) . "\n</head>", $html, 1) : self::style($class) . $html;

        $pos = strripos($html, '</body>');

        return $pos !== false ? substr($html, 0, $pos) . self::script($class) . "\n" . substr($html, $pos) : $html . self::script($class);
    }

    private static function hasGallery(string $html, string $class): bool
    {
        $q = preg_quote($class, '#');

        return (bool) preg_match('#<ul\b[^>]*\bclass\s*=\s*["\']?(?:[^"\'>]*\s)?' . $q . '(?:[\s"\'>])#i', $html);
    }

    private static function style(string $class): string
    {
        $css = (string) @file_get_contents(My::path() . '/css/gallery.css');
        $css = str_replace('GALLERY', $class, (string) preg_replace(['#/\*.*?\*/#s', '#\s*\n\s*#'], ['', ''], $css));

        return '<style id="yagb-css">' . $css . '</style>';
    }

    private static function script(string $class): string
    {
        return '<script src="' . Html::escapeHTML(App::blog()->getPF(My::id() . '/js/gallery.js')) . '" defer'
            . ' data-gallery="' . Html::escapeHTML($class) . '"'
            . ' data-prev="' . Html::escapeHTML(__('Previous image')) . '"'
            . ' data-next="' . Html::escapeHTML(__('Next image')) . '"'
            . ' data-close="' . Html::escapeHTML(__('Close')) . '"'
            . ' data-of="' . Html::escapeHTML(__('%1$s of %2$s')) . '"></script>';
    }
}
