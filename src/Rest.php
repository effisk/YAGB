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
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Exception;
use Throwable;

/**
 * REST service: store one photo of a gallery in the media manager.
 *
 * POST f=yagbUpload, json=1, xd_check, file, folder, basename,
 * index (1-based), post_id (optional). The nonce is checked by Dotclear.
 */
class Rest
{
    private const TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * @param   array<string, mixed>    $get
     * @param   array<string, mixed>    $post
     *
     * @return  array<string, mixed>
     */
    public static function upload(array $get, array $post): array
    {
        try {
            return self::doUpload($post);
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $e) {
            // RestServer only reports Exception
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param   array<string, mixed>    $post
     *
     * @return  array<string, mixed>
     */
    private static function doUpload(array $post): array
    {
        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_MEDIA,
            App::auth()::PERMISSION_MEDIA_ADMIN,
        ]), App::blog()->id())) {
            throw new Exception(__('Permission denied.'));
        }

        $media = App::media();
        if ($media->isRootMissing()) {
            throw new Exception(__('The media folder of this blog is missing.'));
        }

        // The file
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || !isset($file['tmp_name'], $file['error']) || !is_string($file['tmp_name'])) {
            throw new Exception(__('No file received.'));
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(match ((int) $file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('The file is too large for this server.'),
                UPLOAD_ERR_PARTIAL                        => __('The file was only partly received.'),
                default                                   => __('The file could not be received.'),
            });
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception(__('No file received.'));
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new Exception(__('Only JPEG, PNG and WebP images are accepted.'));
        }
        $ext = self::TYPES[$info[2]];

        // Destination folder (created if needed, always inside the media root)
        $folder = My::cleanFolder(is_string($post['folder'] ?? null) ? $post['folder'] : '');
        if ($folder !== '') {
            $target = $media->getRoot() . '/' . $folder;
            if (!is_dir($target)) {
                Files::makeDir($target, true);
            }
        }
        $media->chdir($folder);

        // File name: <basename>-<NN>.<ext>, first free number from index
        $basename = Files::tidyFileName(is_string($post['basename'] ?? null) ? $post['basename'] : '');
        $basename = trim((string) preg_replace('/\.(jpe?g|png|webp|gif)$/i', '', $basename), '-_. ');
        if ($basename === '') {
            throw new Exception(__('Enter a file name.'));
        }
        $index = max(1, (int) ($post['index'] ?? 1));
        $pwd   = $media->getPwd();
        for ($n = $index; ; $n++) {
            $name = sprintf('%s-%02d.%s', $basename, $n, $ext);
            if (!file_exists($pwd . '/' . $name)) {
                break;
            }
            if ($n > $index + 999) {
                throw new Exception(__('Too many files with this name.'));
            }
        }

        $bits = file_get_contents($file['tmp_name']);
        if ($bits === false) {
            throw new Exception(__('The file could not be received.'));
        }

        // Stores the file, creates the thumbnails and calls the other plugins' handlers
        $id = (int) $media->uploadBits($name, $bits);
        if ($id <= 0) {
            throw new Exception(__('The file could not be saved.'));
        }

        $title = is_string($post['title'] ?? null) ? trim($post['title']) : '';
        if ($title !== '') {
            self::setTitle($id, $title);
        }

        // Link the photo to the post (not shown as a public attachment)
        $post_id = (int) ($post['post_id'] ?? 0);
        if ($post_id > 0) {
            try {
                App::postMedia()->addPostMedia($post_id, $id, 'gallery');
            } catch (Throwable) {
            }
        }

        $mf = $media->getFile($id);
        if ($mf === null) {
            throw new Exception(__('The file could not be saved.'));
        }

        $size  = is_string($post['thumb'] ?? null) ? $post['thumb'] : My::config()['thumb_size'];
        $thumb = $mf->media_thumb[$size] ?? null;

        // Width and height of the image shown in the grid
        [$tw, $th] = [(int) $info[0], (int) $info[1]];
        if ($thumb !== null) {
            $thumb_path = self::urlToPath($thumb);
            if ($thumb_path !== null && ($ti = @getimagesize($thumb_path)) !== false) {
                [$tw, $th] = [(int) $ti[0], (int) $ti[1]];
            }
        }

        return [
            'id'     => $id,
            'name'   => $name,
            'url'    => Html::stripHostURL((string) $mf->file_url),
            'thumb'  => Html::stripHostURL((string) ($thumb ?? $mf->file_url)),
            'width'  => $tw,
            'height' => $th,
        ];
    }

    /**
     * Media title = description (shown in the media manager, reused as alt there).
     */
    private static function setTitle(int $id, string $title): void
    {
        try {
            $mf = App::media()->getFile($id);
            if ($mf !== null) {
                $new              = clone $mf;
                $new->media_title = My::fitTitle($title);
                // Same folder and name as now, so that nothing is moved
                $dir           = dirname((string) $mf->relname);
                $new->dir      = $dir === '.' ? '' : $dir;
                $new->basename = basename((string) $mf->relname);
                App::media()->updateFile($mf, $new);
            }
        } catch (Throwable) {
        }
    }

    /**
     * Filesystem path of a media URL, if it is under the media root.
     */
    private static function urlToPath(string $url): ?string
    {
        $root_url = App::media()->getRootUrl();
        if (!str_starts_with($url, $root_url)) {
            $url_path  = parse_url($url, PHP_URL_PATH);
            $root_path = parse_url($root_url, PHP_URL_PATH);
            if (!is_string($url_path) || !is_string($root_path) || !str_starts_with($url_path, $root_path)) {
                return null;
            }
            $relative = substr($url_path, strlen($root_path));
        } else {
            $relative = substr($url, strlen($root_url));
        }

        $path = Path::real(App::media()->getRoot() . '/' . rawurldecode(ltrim($relative, '/')));

        return is_string($path) && is_file($path) ? $path : null;
    }
}
