# Changelog

## 1.0.1 (2026-11-19)

- The media folder suggestions, in the dialog and in the settings, now list every folder of the media folder, read from the disk. They used to show only folders that already contained a file known to the media manager, so empty folders, folders holding only subfolders and folders added by FTP were missing.

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
