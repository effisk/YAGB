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

use Dotclear\Module\MyPlugin;

/**
 * Module helper and settings.
 */
class My extends MyPlugin
{
    public const DEFAULTS = [
        'css_class'    => 'photo-gallery',  // class of the <ul>
        'heading'      => 'h4',             // heading tag above the gallery, or '' for none
        'thumb_size'   => 's',              // Dotclear thumbnail size used in the grid
        'folder'       => '',               // default media folder
        'max_size'     => 1600,             // longest edge in px before upload, 0 = keep
        'quality'      => 85,               // JPEG quality when resizing (1-100)
        'front_assets' => true,             // load the plugin's grid CSS and lightbox on the blog
    ];

    public const HEADINGS = ['h2', 'h3', 'h4', 'h5', 'h6', ''];

    /** Size of Dotclear's media_title column. */
    public const TITLE_MAX = 255;

    /**
     * Settings with defaults applied and values checked.
     *
     * @return array{css_class: string, heading: string, thumb_size: string, folder: string, max_size: int, quality: int, front_assets: bool}
     */
    public static function config(): array
    {
        $s = self::settings();
        $c = self::DEFAULTS;

        if ($s->settingExists('css_class')) {
            $v = trim($s->getStr('css_class', false));
            if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $v)) {
                $c['css_class'] = $v;
            }
        }
        if ($s->settingExists('heading') && in_array($v = $s->getStr('heading', false), self::HEADINGS, true)) {
            $c['heading'] = $v;
        }
        if ($s->settingExists('thumb_size') && preg_match('/^[a-z0-9]{1,4}$/', $v = $s->getStr('thumb_size', false))) {
            $c['thumb_size'] = $v;
        }
        if ($s->settingExists('folder')) {
            $c['folder'] = self::cleanFolder($s->getStr('folder', false));
        }
        if ($s->settingExists('max_size')) {
            $v              = $s->getInt('max_size', false);
            $c['max_size']  = $v === 0 ? 0 : max(320, min(10000, $v));
        }
        if ($s->settingExists('quality')) {
            $c['quality'] = max(1, min(100, $s->getInt('quality', false)));
        }
        if ($s->settingExists('front_assets')) {
            $c['front_assets'] = (bool) $s->getBool('front_assets', false);
        }

        return $c;
    }

    /**
     * A media folder path relative to the media root: no leading/trailing
     * slash, no "." or ".." segments, no hidden segments.
     */
    public static function cleanFolder(string $folder): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $folder)) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) {
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * A media title that fits the media_title column, cut on a word boundary when
     * possible. With $escape, the length is checked after HTML escaping (as Dotclear
     * stores titles escaped), and an entity is never cut in half.
     */
    public static function fitTitle(string $title, bool $escape = true): string
    {
        $title = trim($title);
        $out   = $escape ? \Dotclear\Helper\Html\Html::escapeHTML($title) : $title;
        if (mb_strlen($out) <= self::TITLE_MAX) {
            return $out;
        }

        $cut = mb_substr($title, 0, self::TITLE_MAX - 1);
        while ($cut !== '') {
            $space = mb_strrpos($cut, ' ');
            $try   = ($space !== false && $space > self::TITLE_MAX / 2 ? mb_substr($cut, 0, $space) : $cut) . '…';
            $out   = $escape ? \Dotclear\Helper\Html\Html::escapeHTML($try) : $try;
            if (mb_strlen($out) <= self::TITLE_MAX) {
                return $out;
            }
            $cut = mb_substr($cut, 0, -10);
        }

        return '';
    }
}
