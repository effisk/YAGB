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
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Exception;
use Throwable;

/**
 * Plugin page: settings.
 */
class Manage
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (isset($_POST['yagb_save'])) {
            try {
                $ws  = App::blogWorkspace();
                $s   = My::settings();
                $str = fn (string $k): string => isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : '';

                $class = $str('yagb_css_class');
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $class)) {
                    throw new Exception(__('The class name may only contain letters, digits, hyphens and underscores, and must start with a letter.'));
                }
                $heading = $str('yagb_heading');
                if (!in_array($heading, My::HEADINGS, true)) {
                    $heading = My::DEFAULTS['heading'];
                }
                $size = $str('yagb_thumb_size');
                if (!array_key_exists($size, App::media()->getThumbSizes())) {
                    $size = My::DEFAULTS['thumb_size'];
                }
                $max = (int) $str('yagb_max_size');
                $max = $max <= 0 ? 0 : max(320, min(10000, $max));

                $s->put('css_class', $class, $ws::NS_STRING);
                $s->put('heading', $heading, $ws::NS_STRING);
                $s->put('thumb_size', $size, $ws::NS_STRING);
                $s->put('folder', My::cleanFolder($str('yagb_folder')), $ws::NS_STRING);
                $s->put('max_size', $max, $ws::NS_INT);
                $s->put('quality', max(1, min(100, (int) $str('yagb_quality'))), $ws::NS_INT);
                $s->put('front_assets', !empty($_POST['yagb_front_assets']), $ws::NS_BOOL);

                App::blog()->triggerBlog();
                App::backend()->notices()->addSuccessNotice(__('Settings have been successfully updated.'));
                My::redirect();
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    public static function render(): void
    {
        $c = My::config();
        $e = Html::escapeHTML(...);

        App::backend()->page()->openModule(My::name(), My::cssLoad('editor'));

        echo App::backend()->page()->breadcrumb([
            $e(App::blog()->name()) => '',
            __('Yet Another Gallery Builder')   => '',
        ]);
        echo App::backend()->notices()->getNotices();

        $hidden = '';
        foreach (My::hiddenFields() as $field) {
            $hidden .= $field->render();
        }

        $headings = '';
        foreach (My::HEADINGS as $h) {
            $headings .= '<option value="' . $h . '"' . ($c['heading'] === $h ? ' selected' : '') . '>'
                . ($h === '' ? $e(__('No heading')) : '&lt;' . $h . '&gt;') . '</option>';
        }

        $sizes = '';
        try {
            foreach (App::media()->getThumbSizes() as $code => $def) {
                $label = sprintf('%s (%s, %d px)', __((string) ($def[2] ?? $code)), $code, (int) $def[0]);
                $sizes .= '<option value="' . $e((string) $code) . '"' . ($c['thumb_size'] === (string) $code ? ' selected' : '') . '>' . $e($label) . '</option>';
            }
        } catch (Throwable) {
        }

        $folders = '';
        foreach (My::mediaFolders() as $dir) {
            $folders .= '<option value="' . $e($dir) . '">';
        }

        echo '<div class="fieldset"><h3>' . $e(__('How to use')) . '</h3>'
            . '<p>' . $e(__('In the post editor, click the "Photo gallery" button in the toolbar. Choose or drop the photos, write a description for each, put them in order, then click "Upload and insert". The photos are added to the media manager and the gallery is inserted at the cursor as plain HTML, which you can edit like the rest of the post.')) . '</p>'
            . '<p class="form-note">' . $e(__('The button is available in the default (legacy) editor, in both its visual and source modes.')) . '</p>'
            . '</div>';

        echo '<form method="post" action="' . App::backend()->getPageURL() . '" id="yagb-settings">'
            . '<div class="fieldset"><h3>' . $e(__('Gallery HTML')) . '</h3>'
            . '<p><label for="yagb_css_class">' . $e(__('Class of the gallery list:')) . '</label> '
            . '<input type="text" id="yagb_css_class" name="yagb_css_class" size="30" value="' . $e($c['css_class']) . '"></p>'
            . '<p class="form-note">' . $e(__('Used as <ul class="…">. If your theme already styles galleries with its own class, enter that class here.')) . '</p>'
            . '<p><label for="yagb_heading">' . $e(__('Heading above a gallery:')) . '</label> '
            . '<select id="yagb_heading" name="yagb_heading">' . $headings . '</select></p>'
            . '<p><label for="yagb_thumb_size">' . $e(__('Image size shown in the grid:')) . '</label> '
            . '<select id="yagb_thumb_size" name="yagb_thumb_size">' . $sizes . '</select></p>'
            . '<p class="form-note">' . $e(__('Each image links to the full-size photo.')) . '</p>'
            . '</div>'

            . '<div class="fieldset"><h3>' . $e(__('Uploads')) . '</h3>'
            . '<p><label for="yagb_folder">' . $e(__('Default media folder:')) . '</label> '
            . '<input type="text" id="yagb_folder" name="yagb_folder" size="30" list="yagb_folders" value="' . $e($c['folder']) . '"><datalist id="yagb_folders">' . $folders . '</datalist></p>'
            . '<p class="form-note">' . $e(__('A folder inside the blog\'s media folder (the one shown in the media manager), not a server path or a URL. Leave empty for the top level. Separate subfolders with a slash, for example shows/2026; slashes at the start or end are not needed and are removed. Folders that do not exist yet are created at the first upload. This is only the default: it can be changed in the dialog for each gallery.')) . '</p>'
            . '<p><label for="yagb_max_size">' . $e(__('Resize photos before upload to (longest edge, px, 0 = keep the original size):')) . '</label> '
            . '<input type="number" id="yagb_max_size" name="yagb_max_size" min="0" max="10000" step="1" value="' . $c['max_size'] . '"></p>'
            . '<p class="form-note">' . $e(__('Larger photos are resized in the browser, so big camera files never hit the server upload limit, and resized photos lose their camera and location data. Sideways phone photos are always turned the right way up.')) . '</p>'
            . '<p><label for="yagb_quality">' . $e(__('JPEG quality when resizing (1-100):')) . '</label> '
            . '<input type="number" id="yagb_quality" name="yagb_quality" min="1" max="100" value="' . $c['quality'] . '"></p>'
            . '</div>'

            . '<div class="fieldset"><h3>' . $e(__('On the blog')) . '</h3>'
            . '<p><label class="classic"><input type="checkbox" name="yagb_front_assets" value="1"' . ($c['front_assets'] ? ' checked' : '') . '> '
            . $e(__('Show galleries as a grid with a lightbox (CSS and script are added only to pages that contain a gallery)')) . '</label></p>'
            . '<p class="form-note">' . $e(__('Turn this off if your theme already styles galleries and opens photos in its own lightbox.')) . '</p>'
            . '</div>'

            . '<p class="form-buttons">' . $hidden
            . '<input type="submit" name="yagb_save" value="' . $e(__('Save')) . '"></p>'
            . '</form>';

        App::backend()->page()->closeModule();
    }
}
