# Changelog

## 1.0.2 (2026-11-19)

- A gallery inserted inside a paragraph (in visual or HTML source mode) no longer ends up nested in it, which left empty paragraphs around it. An empty paragraph is replaced; otherwise the paragraph is split at the cursor.
- Installing or updating the plugin now marks every blog as updated, so page caches such as Static Cache stop serving pages rendered before the plugin was installed, without the grid and lightbox.

## 1.0.1 (2026-11-19)

- The media folder suggestions, in the dialog and in the settings, now list every folder of the media folder, read from the disk. They used to show only folders that already contained a file known to the media manager, so empty folders, folders holding only subfolders and folders added by FTP were missing.
- The dialog has a close button (×) at the top right instead of a Cancel button; Esc does the same. As before, photos already uploaded are inserted when it is closed. The Upload and insert button is at the bottom right.

## 1.0 (2026-11-19)

First release.

- **Photo gallery** button in the default editor (visual and source modes; HTML, wiki and Markdown syntax).
- Multi-photo upload to the media manager, with numbered file names, a shared alt-text ending, per-photo descriptions and drag/arrow ordering.
- Photos resized in the browser before upload (configurable), with EXIF orientation applied.
- Upload resumes after a failed photo without sending the others twice.
- Plain-HTML gallery inserted at the cursor. On the blog, an optional responsive grid and an accessible lightbox, added only to pages with a gallery. They are part of the rendered post, so page caches such as Static Cache keep them.
- Photos whose embedded caption is longer than 255 characters no longer fail with "Data too long for column 'media_title'" on MySQL (this also fixes uploads through the media manager).
- Clear help for the media folder: a folder inside the blog's media folder, subfolders separated with a slash, created at the first upload.
- English and French.
