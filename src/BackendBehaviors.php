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
use Dotclear\Database\Cursor;
use Throwable;

class BackendBehaviors
{
    /**
     * Add the "Gallery" button to the legacy editor of posts and pages.
     */
    public static function adminPostEditor(string $editor = '', string $context = '', array $tags = [], string $syntax = ''): string
    {
        if ($editor !== 'dcLegacyEditor' || !in_array($context, ['post', 'page'], true)) {
            return '';
        }

        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_MEDIA,
            App::auth()::PERMISSION_MEDIA_ADMIN,
        ]), App::blog()->id())) {
            return '';
        }

        $config  = My::config();
        $folders = [];

        $folders = My::mediaFolders();

        $sizes = [];
        try {
            foreach (App::media()->getThumbSizes() as $code => $size) {
                $sizes[(string) $code] = (int) $size[0];
            }
        } catch (Throwable) {
        }

        return App::backend()->page()->jsJson('yagb', [
            'config' => [
                'cssClass'   => $config['css_class'],
                'heading'    => $config['heading'],
                'thumbSize'  => $config['thumb_size'],
                'thumbWidth' => $sizes[$config['thumb_size']] ?? 0,
                'folder'     => $config['folder'],
                'maxSize'    => $config['max_size'],
                'quality'    => $config['quality'],
                'maxUpload'  => (int) App::config()->maxUploadSize(),
                'folders'    => $folders,
                'icon'       => My::fileURL('/icon.svg'),
                'iconDark'   => My::fileURL('/icon-dark.svg'),
            ],
            'msg' => [
                'button'        => __('Photo gallery'),
                'title'         => __('Insert a photo gallery'),
                'heading'       => __('Heading (optional):'),
                'folder'        => __('Media folder:'),
                'folderHelp'    => __('Inside the media folder, e.g. shows or shows/2026. Empty = top level. Created if needed.'),
                'basename'      => __('File names:'),
                'basenameHelp'  => __('Photos are saved as name-01.jpg, name-02.jpg…'),
                'suffix'        => __('Added to every alt text:'),
                'suffixHelp'    => __('For example: "in Hamlet at the Globe. Photo by Jane Doe"'),
                'maxSize'       => __('Resize before upload to (longest edge):'),
                'keep'          => __('Keep original size'),
                'drop'          => __('Drop photos here or choose files'),
                'choose'        => __('Choose files'),
                'description'   => __('Description'),
                'altPreview'    => __('Alt text:'),
                'moveUp'        => __('Move up'),
                'moveDown'      => __('Move down'),
                'remove'        => __('Remove'),
                'insert'        => __('Upload and insert'),
                'cancel'        => __('Cancel'),
                'uploading'     => __('Uploading %1$s of %2$s…'),
                'notImage'      => __('%s is not a JPEG, PNG or WebP image.'),
                'tooBig'        => __('%s is too large to upload (maximum %s).'),
                'noPhotos'      => __('Add at least one photo.'),
                'noBasename'    => __('Enter a file name.'),
                'failed'        => __('Upload failed for %1$s: %2$s'),
                'retry'         => __('Click "Upload and insert" to try again; photos already uploaded are not sent twice.'),
                'emptyAlt'      => __('%s photo(s) have no alt text. Insert anyway?'),
            ],
        ]) .
        My::jsLoad('editor') .
        My::cssLoad('editor');
    }

    /**
     * Keep a title read from the photo's metadata within the media_title column (255).
     */
    public static function coreBeforeImageMetaCreate(Cursor $cur): string
    {
        if ($cur->isField('media_title')) {
            $title = $cur->strField('media_title', true);
            if (is_string($title) && mb_strlen($title) > My::TITLE_MAX) {
                $cur->setStrField('media_title', My::fitTitle($title, false));
            }
        }

        return '';
    }
}
