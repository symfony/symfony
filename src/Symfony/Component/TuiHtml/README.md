TuiHtml Component
=================

The TuiHtml component draws HTML and CSS in the terminal, on top of the Tui component: views written as markup,
style sheets and Twig templates become Tui widget trees, with forms, focus, dialogs, scrolling and pointer events.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Installation
------------

```bash
composer require symfony/tui-html
```

It requires PHP 8.4.1 or later and the DOM extension. The rest is optional:

 * the GD extension draws images and SVG; without it, they show their text alternative;
 * the intl extension draws right-to-left text in visual order;
 * `twig/twig` builds views from templates, and `symfony/ux-twig-component` from components;
 * `symfony/asset-mapper` loads style sheets and images from mapped assets;
 * `league/commonmark` and `tempest/highlight` render `<tui-markdown>`, drawn as written without them;
 * `org_heigl/hyphenator` hyphenates words, see below.

Usage
-----

Select the HTML renderer before starting Tui, then mount a view:

```php
use Symfony\Component\Tui\Tui;
use Symfony\Component\TuiHtml\Event\FormSubmitEvent;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\Renderer as HtmlRenderer;

$tui = new Tui();
$tui->setRenderer(new HtmlRenderer());

$view = (new HtmlBuilder())->build(<<<'HTML'
    <style>
      form { display: grid; grid-template-columns: 12ch 1fr; gap: 0 1ch; max-width: 50ch }
      h1 { color: green }
      button { grid-column: 2 }
    </style>
    <h1>New project</h1>
    <form>
      <label for="name">Name</label> <input id="name" name="name" required>
      <label for="type">Type</label>
      <select id="type" name="type"><option>Library</option><option>Application</option></select>
      <button>Create</button>
    </form>
    HTML);
$view->querySelector('form')->onSubmit(static function (FormSubmitEvent $event) use ($tui): void {
    $tui->stop();
    printf("Creating %s (%s)\n", $event->getValues()['name'], $event->getValues()['type']);
});
$view->mount($tui);
$tui->run();
```

F6 and Shift+F6 move the focus, like Tab in a browser; Enter submits and Space toggles. The view finds widgets with
selectors (`querySelector()`, `getById()`), and its widgets dispatch events: `FormSubmitEvent`, `LinkEvent`,
`ToggleEvent`, `CloseEvent` and others in the `Event` namespace.

The renderer is selected once, before the start. Native Tui widgets and style sheets can be used next to HTML views.
Media features (color scheme, density, pointer, color depth) are set on the renderer with `setMediaContext()`.

Twig templates
--------------

`TuiRenderer` builds views from Twig templates and blocks:

```php
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Twig\TuiRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$twig = new Environment(new FilesystemLoader(__DIR__.'/templates'));
$view = (new TuiRenderer($twig, new HtmlBuilder()))->render('settings.html.twig', [
    'title' => 'Languages',
    'languages' => ['PHP', 'Twig', 'CSS'],
    'current' => 'Twig',
]);
```

```twig
{# templates/settings.html.twig #}
<style>li.active { font-weight: bold; color: cyan }</style>
<h1>{{ title }}</h1>
<ul>
  {% for language in languages %}
    <li{% if language == current %} class="active"{% endif %}>{{ language }}</li>
  {% endfor %}
</ul>
```

Symfony UX TwigComponent components (`<twig:Card>`) render to HTML before the builder sees it: the docblock of
`TuiRenderer` shows how to wire it without the bundle. `TuiExtension` adds the `condensed` test for markup that depends
on the density of the terminal.

Loading resources
-----------------

Nothing is loaded from the network or the filesystem by default: `<link rel="stylesheet">`, `@import`, `<img src>`
and CSS `url()` only read `data:` URLs. A style sheet that cannot be loaded is reported to the logger, an image that
cannot be read shows its text alternative.

 * Pass a `StylesheetLoaderInterface` to `CssStyleSheet` to load style sheets and the images they use:
   `FilesystemStylesheetLoader` reads paths inside the directories it is given (never a parent of them, whatever the
   scheme of the address), and `AssetMapperStylesheetLoader` reads mapped assets only.
 * Register an `ImageElement` with a loader closure in the `ElementRegistry` to load the images of `<img>` elements.

Scripts and `on*` attributes are never run. Following a link dispatches a `LinkEvent` with its address as written:
the application decides what to do with it.

Untrusted documents
-------------------

Terminal escape sequences in text, attributes and CSS strings are removed before drawing. The work a document can
cause is bounded, among others:

 * `HtmlBuilder::build()` refuses a document larger than 4 MB by default (building takes about 65 MB of memory for
   each MB of HTML), see the `$maxBytes` argument of `HtmlBuilder`; a style sheet larger than 8 MB, its imports
   included, is skipped;
 * elements nested deeper than 512 levels are drawn as siblings, like the HTML parser of Chromium;
 * CSS rules nested deeper than 64 levels, selectors nested deeper than 128, and more than 256 `@import` rules
   are ignored, as is a value longer than 64 KB once its `var()` references are substituted;
 * images are decoded up to 4 million pixels, and SVG documents defining entities are rejected.

Hyphenation
-----------

TuiHtml bundles no hyphenation dictionary and hyphenates nothing by default: words break at spaces and at the soft
hyphens (`&shy;`) of the document. To break words with `hyphens: auto`, give `CssStyleSheet` a `HyphenatorInterface`.
`HeiglHyphenator` uses the dictionaries of `org_heigl/hyphenator`, about 40 languages:

```bash
composer require org_heigl/hyphenator
```

```php
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Hyphenation\HeiglHyphenator;

$builder = new HtmlBuilder(new CssStyleSheet(hyphenator: new HeiglHyphenator()));
$view = $builder->build('<p lang="en" style="hyphens: auto; width: 12ch">Hyphenation breaks extraordinarily long words.</p>');
```

The language comes from the `lang` attribute, inherited like in HTML. Text without a language, in a language without a
dictionary, or in an address (a URL, an e-mail) wraps whole. The licenses of the dictionaries vary by language: see
the package. Another library is used by implementing `HyphenatorInterface`, which returns where a word breaks.

Compatibility
-------------

[COMPATIBILITY.md](COMPATIBILITY.md) describes what HTML and CSS TuiHtml supports, and how a terminal draws them
differently from a browser. `CompatibilityReport::createDefault()` lists each element and CSS feature with one of four
levels, `supported`, `partial`, `ignored` and `unsupported`, and a note; `Resources/compatibility.json` is that report.
Give `HtmlBuilder` and `CssStyleSheet` a PSR-3 logger to hear about the unsupported features a document uses.

TuiHtml is not a browser and does not aim to draw web pages like one: it draws the views of terminal applications.
Scripts never run, nothing is fetched by itself, lengths are rounded to cells, and the terminal chooses the font.

Performance
-----------

Building a view parses the document and its style sheets once; building takes about 65 MB of memory for each MB of
HTML, so views are best kept to the size of a screen or a few, not to that of a long web page. Each frame then draws
only what changed: a widget whose content, state and room are unchanged reuses its render. A focus move (F6) costs in proportion to the number of
widgets that take the focus. The native widgets of Tui are drawn by the TuiHtml renderer as well, and a TUI that does
not select it pays nothing for it.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/tui.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
