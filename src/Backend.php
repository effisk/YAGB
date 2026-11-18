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
use Dotclear\Core\Backend\Menus;
use Dotclear\Helper\Process\TraitProcess;

/**
 * Backend: settings page, editor button and upload service.
 */
class Backend
{
    use TraitProcess;

    public static function init(): bool
    {
        // Dead but useful code (for l10n)
        __('Yet Another Gallery Builder');
        __('Upload photos from the post editor and insert them as a gallery');

        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        My::addBackendMenuItem(Menus::MENU_BLOG);

        App::behavior()->addBehavior('adminPostEditor', BackendBehaviors::adminPostEditor(...));

        App::rest()->addFunction('yagbUpload', Rest::upload(...));

        // Dotclear copies the caption embedded in a photo (IPTC/XMP Title) into the media
        // title without checking its length; a long press caption then breaks the upload
        // on MySQL/PostgreSQL (media_title is varchar(255)). Applies to every upload.
        App::behavior()->addBehavior('coreBeforeImageMetaCreate', BackendBehaviors::coreBeforeImageMetaCreate(...));

        return true;
    }
}
