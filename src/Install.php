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

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * Install: global default settings (each blog can override them).
 */
class Install
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $ws = App::blogWorkspace();
        $s  = My::settings();
        $d  = My::DEFAULTS;

        $s->put('css_class', $d['css_class'], $ws::NS_STRING, 'Class of the gallery list', false, true);
        $s->put('heading', $d['heading'], $ws::NS_STRING, 'Heading tag above a gallery', false, true);
        $s->put('thumb_size', $d['thumb_size'], $ws::NS_STRING, 'Thumbnail size used in the grid', false, true);
        $s->put('folder', $d['folder'], $ws::NS_STRING, 'Default media folder', false, true);
        $s->put('max_size', $d['max_size'], $ws::NS_INT, 'Longest edge before upload (0 = keep)', false, true);
        $s->put('quality', $d['quality'], $ws::NS_INT, 'JPEG quality when resizing', false, true);
        $s->put('front_assets', $d['front_assets'], $ws::NS_BOOL, 'Load the grid CSS and lightbox on the blog', false, true);

        return true;
    }
}
