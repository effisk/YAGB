# Yet Another Gallery Builder (YAGB)

[![Dotclear](https://img.shields.io/badge/dotclear-2.36%2B-137bbb.svg)](https://dotclear.org/download)
[![License](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)

A [Dotclear](https://dotclear.org) plugin to build photo galleries in posts: pick the photos, describe and order them, and one click uploads them and inserts the gallery.

*Une description en français suit la version anglaise.*

## What it does

The post editor gets a **Photo gallery** button. It opens a dialog where you:

1. **Add the photos:** drop them on the dialog or choose them, several at a time (JPEG, PNG, WebP).
2. **Set the common details:** a heading, the media folder (created if needed), a file name, and a text added to every alt text (for example `in Hamlet at the Globe. Photo by Jane Doe`).
3. **Describe and order them:** write a short description per photo (the dialog shows each full alt text as you type), then put them in order with the arrows or by dragging.
4. **Click Upload and insert.** The photos are resized in the browser if needed, uploaded to the media manager as `name-01.jpg`, `name-02.jpg`…, and the gallery is inserted at the cursor.

The gallery is **plain HTML**, so it stays editable like the rest of the post and keeps working if the plugin is turned off:

```html
<h4>Hamlet at the Globe, photographed by Jane Doe</h4>
<ul class="photo-gallery">
<li><a href="/public/shows/hamlet-production-01.jpg"><img src="/public/shows/.hamlet-production-01_s.jpg" alt="Hamlet and Ophelia in Hamlet at the Globe. Photo by Jane Doe" width="240" height="160" loading="lazy"></a></li>
<li>…</li>
</ul>
```

On the blog, galleries show as a responsive grid. Clicking a photo opens a lightbox with previous/next buttons, keyboard and swipe support, and the alt text as caption. The CSS and script are only added to pages that contain a gallery. They are part of the post's HTML, so page-cache plugins such as Static Cache keep them.

Other details:

- **Media manager:** Dotclear makes its usual thumbnails. Each description is saved as the media title, and on an existing post the photos are linked to it, without appearing in its public attachments.
- **Resizing:** large photos are scaled down in the browser before upload (1600 px on the longest edge by default), so big camera files never hit the server's upload limit. Resized photos lose their camera and location data, and sideways phone photos are always turned the right way up.
- **No overwriting:** if `name-01.jpg` already exists, numbering continues from the next free number.
- **Interrupted uploads:** if one photo fails, the dialog says which one and why. Fix or remove it, then click again: photos already uploaded are not sent twice.
- **Works with WebP images:** if the [WebP images](https://github.com/effisk/webpImages) plugin is installed, gallery photos get their WebP copies on upload and are served as WebP.
- **Languages:** English and French.

## Requirements

- Dotclear 2.36 or later (tested on 2.40).
- The default ("legacy") editor, in visual or source mode, with the HTML, wiki or Markdown syntax. CKEditor is not supported yet.
- The media permission on the blog.

## Installation

- From the [releases](https://github.com/effisk/YAGB/releases): download `plugin-YAGB-x.y.z.zip`. Then in Dotclear, go to *System settings → Plugins management → Install or upgrade manually* and upload it.
- Or unzip it into your `plugins/` folder, so that you have `plugins/YAGB/_define.php`.

Once installed, Dotclear's plugin manager offers new versions automatically (from this repository's `dcstore.xml`).

## Settings

*Blog → Yet Another Gallery Builder*

| Setting | Default | |
|---|---|---|
| Class of the gallery list | `photo-gallery` | If your theme already styles galleries with its own class, use that class. |
| Heading above a gallery | `<h4>` | Or no heading. |
| Image size shown in the grid | small (240 px) | Any of the media manager's thumbnail sizes. Each image links to the full-size photo. |
| Default media folder | (top level) | A folder inside the blog's media folder, such as `shows` or `shows/2026` (no slash needed at the start or end). Created at the first upload if it does not exist. Can be changed in the dialog for each gallery. |
| Resize before upload | 1600 px | Longest edge. 0 keeps the original size. |
| JPEG quality when resizing | 85 | |
| Show galleries as a grid with a lightbox | on | Turn it off if your theme provides its own gallery styles and lightbox. |

## Using your theme's styles instead

If your theme already has a gallery grid and lightbox for a class such as `my-gallery`:

1. set *Class of the gallery list* to `my-gallery`;
2. untick *Show galleries as a grid with a lightbox*.

Galleries are then inserted with your class, and the plugin adds nothing to the blog's pages.

## Uninstalling

Delete the plugin in the plugin manager. Galleries already in posts keep working: they are plain HTML. Without the plugin's CSS they show as a list of thumbnails, unless your theme styles the class.

## Translations

The plugin uses Dotclear's usual locale files: `locales/<lang>/main.po`, with `main.lang.php` generated from it. To add a language, copy `locales/fr/main.po` to `locales/<lang>/main.po`, translate the `msgstr` lines, and send a pull request.

---

## En français

**Yet Another Gallery Builder** (YAGB) ajoute un bouton **Galerie photos** à l'éditeur de billets.

- **Utilisation :** choisissez ou déposez les photos, donnez un titre, un dossier, un nom de fichier et un texte ajouté à chaque texte alternatif (par exemple « dans Hamlet au Globe. Photo de Jeanne Dupont »). Décrivez chaque photo, mettez-les dans l'ordre, puis cliquez sur **Envoyer et insérer**.
- **Résultat :** les photos sont redimensionnées dans le navigateur si besoin, envoyées dans le gestionnaire de médias (`nom-01.jpg`, `nom-02.jpg`…), et la galerie est insérée au curseur en HTML simple.
- **Sur le blog :** une grille et une visionneuse, ajoutées seulement aux pages qui contiennent une galerie. Si votre thème a déjà les siennes, indiquez sa classe dans les réglages et désactivez celles du plugin.
- **Prérequis :** Dotclear 2.36 ou plus, l'éditeur par défaut (legacy), en mode visuel ou source.
- **Installation :** téléchargez `plugin-YAGB-x.y.z.zip` depuis les [releases](https://github.com/effisk/YAGB/releases), puis *Réglages système → Gestion des plugins → Installer ou mettre à jour manuellement*.

## License

[AGPL-3.0](LICENSE), like Dotclear.
