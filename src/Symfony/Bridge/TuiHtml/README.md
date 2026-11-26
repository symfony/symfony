TuiHtml Bridge
==============

The TuiHtml bridge mounts HTML documents in Tui applications. The standalone HtmlRenderer component owns document rendering; the bridge supplies terminal measurement, painting and input integration.
The retained compatibility path still translates markup into Tui widgets while its supported behavior migrates.

The package is `symfony/tui-html-bridge`, in `Symfony\Bridge\TuiHtml`, following the existing top-level Symfony bridge convention (`twig-bridge`, `doctrine-bridge`).
It depends on Tui and the standalone `symfony/html-renderer` component.

Migration is in progress: `HtmlBuilder` and `Render\Renderer` below preserve the established widget path and compliance behavior.
`DocumentWidget` is the new display-list integration; it does not yet replace every feature of that path.
This proposal targets Symfony 8.3. The independent pipeline remains opt-in while terminal parity, shared-algorithm extraction and measured performance regressions are resolved. The component README describes the rendering architecture and current limitations.

The opt-in facade uses two narrow Tui integration points. `SelfRenderingInterface` lets only the HTML root provide its complete display-list output while retaining native caching and lifecycle hooks. `TabsWidget::renderWithContent()` reuses the native tab chrome and its allocated content viewport, allowing HtmlRenderer to lay out the active HTML body with the same prepared cascade. The actual tab widget, focus handling and events remain in Tui; no HTML or CSS algorithms move into it. The bridge keeps native tab chrome and natural flow height separate from the active body's paint extent, so a popup can paint and receive input beyond that body without moving the chrome. Extra terminal rows are bounded by the outer viewport during intrinsic probes. Native dropdown and combobox popups retain their opaque content surface: blank cells clear underlying text using the existing backdrop, or the terminal default when its RGB color is unknown. Other native content remains transparent. These portable native-content lists require the bridge backend and matching terminal measurement; popup border background differences remain under investigation. The independent cascade resets omitted shorthand components: an inline `border:1px solid` sets `border-color:currentColor` and outranks a normal `input[list]:open` color rule, even while the native popup is open. The retained mapper could preserve that rule's color instead; this is an intentional CSS cascade correction, not a missing open state.

**This bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Installation
------------

```bash
composer require symfony/tui-html-bridge
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
use Symfony\Bridge\TuiHtml\Event\FormSubmitEvent;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\Renderer as HtmlRenderer;

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

The renderer is selected once, before the start, or decorated by the renderer selected when that one implements
`RendererDecoratorInterface`. Native Tui widgets and style sheets can be used next to HTML views.
Media features (color scheme, density, pointer, color depth) are set on the renderer with `setMediaContext()`.

Standalone engine integration
-----------------------------

The new document widget consumes the engine's display list and uses Tui's existing lifecycle and focus manager:

```php
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Tui;

$metrics = new TerminalTextMeasurer();
$session = (new HtmlRenderer())->createSession(
    '<style>body { color: white; line-height: 16px }</style><p>Hello</p><input name="name">',
    new RenderingContext(640, 384, $metrics),
);
$tui = new Tui();
$tui->add(new DocumentWidget($session, $metrics));
$tui->run();
```

Mounted widgets subscribe to session invalidation, so programmatic form-state changes request a native Tui redraw. After mutating the native DOM directly, call `$session->invalidate()`; PHP DOM mutations do not emit notifications. The subscription is removed when the widget detaches.

The adapter updates viewport metrics when Tui resizes. Pointer input captured by the application can be passed to `DocumentWidget::handlePointer($row, $column, $activate)`. For press/release and dragging, use `handlePointerGesture($type, $row, $column, $button, $shift)` with `down`, `move` or `up`; the engine owns selection, active state and light dismissal.
Forward captured wheel input with `DocumentWidget::scrollAt($row, $column, $rows, $columns = 0)`. Coordinates are local to the document's rendered content viewport, and deltas are terminal cells. Unlike the retained `HtmlView::scrollAt()`, this method does not take absolute screen coordinates; the application must account for the widget's screen position, clipping and any native border or padding when routing captured input. Tui does not automatically enable or decode mouse reporting: capture and target selection remain application responsibilities. The engine owns scroll chaining and CSS overscroll containment.

The engine receives document layout coordinates; conversion and cell rounding remain in the bridge.
The widget translates typing, backspace, textarea newlines, checkbox/radio activation, form submission and Tab navigation. Page Up/Down and Left/Right scroll the nearest eligible container when the focused control does not own the key; without document focus, the first eligible container is used. Textareas and selection lists retain their own paging behavior.
Crossing the document focus boundary hands control to Tui's focus manager.

The widget supplies terminal user-agent styles, including border-box sizing, control prompts and glyphs, and the existing default document spacing. Author CSS overrides these defaults. Set `RenderingContext::userAgentStylesheet` to supply an explicit policy instead. `DocumentWidget::setDensity(Density::Condensed)` selects compact spacing; a document's `data-density="regular"` or `data-density="condensed"` overrides it for that subtree. The `:condensed` and `:regular` pseudo-classes belong to this bridge. The bridge translates terminal density into host-maintained document state; HtmlRenderer gives them no built-in meaning. The bridge supplies opaque aliases for terminal palette indices, bright color names and existing Tailwind shades through `RenderingContext::colors`; explicit caller aliases take precedence. Standard CSS and system colors retain engine semantics. Progress/meter percentages and range value labels are host-owned UA declarations associated with native elements, using shared numeric models and terminal formatting. Updating a value does not rebuild the rendering context or generate structural CSS selectors. Explicit caller UA policy disables these defaults; author pseudo-element content remains authoritative. Very narrow controls can clip labels, and the graphical track/thumb approximation does not reproduce every retained widget glyph.

The default terminal device policy reserves outside list-marker room during layout, using the engine's measured sibling-group width. It adds this gutter to authored padding, preserving ordinary list-item text spacing without rewriting computed CSS or performing an additional layout. Inside and hidden markers receive no gutter. Explicit caller UA policies disable the generated default reservation; an explicitly supplied device policy remains authoritative. Nested block children stay inside the list item's content box. The retained widget path hung its marker on the first text widget and could outdent later child blocks, so nested indentation can differ. A zero-gutter device policy retains plain CSS outside-marker geometry.

Terminal text spacing preserves the native profile: positive spacing starts at three quarters of a cell, letter spacing inserts gaps between grapheme clusters but not after the last cluster, and negative spacing is ignored. Word spacing also expands spaces inserted by letter spacing, as in the existing widget path. Measurement, caret positions and painting share this adaptation, including Unicode wide and combining characters and the existing bidirectional resolver. Expanded device text is limited to 8 MiB before gap allocation. These terminal approximations do not change the engine's signed fractional CSS spacing.

Default buttons, closed dropdowns, checkboxes and radios use UA focus colors, with an underline when forced colors remove foreground/background cues. Author focus styles remain authoritative. This follows CSS precedence instead of the retained renderer's final SGR reverse: authored colors and pseudo-element colors stay as declared. The default focus background is clipped to the content box; a border using currentColor follows the focused text color and can differ from the retained border presentation. Open pickers and listboxes keep their separate selection presentation.

When the terminal supports kitty text sizing (OSC 66), enable `MediaContext::withTextSizing()` on the widget and install the protocol-aware frame writer through Tui's existing renderer extension point:

```php
use Symfony\Bridge\TuiHtml\Render\TerminalRenderer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;

$tui->setRenderer(new TerminalRenderer($tui->getRenderer())); // before starting Tui
$widget->setMediaContext((new MediaContext())->withTextSizing());
```

The decorator delegates native widget rendering and focus policy and reuses the bridge's existing terminal frame writer. Font sizes remain logical CSS pixels and use whole scales from one to four; device coordinates retain the original cell metrics at every pixel density. The default terminal UA uses a fixed cell line height, expanded to fit scaled glyphs; authored line heights override that default. Clipped or overpainted scaled graphemes are omitted. An attached application without a compatible frame writer falls back to ordinary text size; headless rendering can explicitly produce scaled-text markers. Virtual-terminal tests and snapshots verify reserved cells and emitted OSC 66 bytes, not rasterized glyphs on a physical terminal.

A terminal has fixed font metrics and whole cells. The adapter draws styled Unicode text, backgrounds, per-side borders, gradients, images/SVG, directional clips and translated drawing operations. It reuses Tui's Unicode and bidirectional algorithms, preserves visual caret positions, reuses the retained Unicode superscript/subscript approximation and schedules active animations through Tui's existing runtime. Opacity groups dim terminal text and omit zero-opacity content; full color-layer compositing and scale/rotation are not reproduced; mixed-style bidirectional paragraphs, disjoint bidirectional selections and popup controls still have limitations. Partial gradient transparency adapts background cells rather than compositing every glyph. Border-image sources color the existing border glyphs; slicing, repeat modes and outsets are not reproduced. Resolved gradient colors can differ slightly from the retained renderer's percentage-based color rounding. Color functions use CSS color operands before terminal adaptation. For example, the retained terminal palette uses yellow `[205, 205, 0]`, blue `[0, 0, 238]` and white `[229, 229, 229]`; CSS functions resolve those names to `[255, 255, 0]`, `[0, 0, 255]` and `[255, 255, 255]`. Direct named colors still use the terminal palette, while resolved function results use numeric colors. Consequently `light-dark()` and color mixes can differ visibly from the retained renderer even when their text and geometry match. The retained widget renderer continues to support its existing terminal approximations; these paths must converge before the standalone refactor is complete.

Twig templates
--------------

`DocumentRenderer` evaluates templates into native document sessions for the display-list path:

```php
use Symfony\Bridge\TuiHtml\Twig\DocumentRenderer;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\RenderingContext;

$templates = new DocumentRenderer($twig);
$session = $templates->render('settings.html.twig', new RenderingContext(640, 384), ['title' => 'Languages']);
$tui->add(new DocumentWidget($session));
```

`renderBlock($name, $block, $renderingContext, $variables)` evaluates one block. Twig and UX component evaluation finish before HtmlRenderer processes the document; no template runtime enters the engine. The session remains available for form callbacks and live state changes.

The retained `TuiRenderer` builds widget views from Twig templates and blocks:

```php
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Twig\TuiRenderer;
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
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Hyphenation\HeiglHyphenator;

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

Color-filter display-list groups reuse HtmlRenderer's CSS matrices and the bridge's existing color rewriter. Full inversion alone retains the terminal foreground/background reverse approximation, including forced-color mode. Other functions transform the explicit cell colors; the terminal's actual default background remains unknown. Nested groups preserve untouched cells, existing parent backgrounds, wide graphemes and scaled-text payloads. Blur and drop-shadow on the display-list path remain migration work.

CSS declarations, selectors, parsed rules, animation transitions, color models, SVG names, blend modes, compatibility metadata and shared exceptions use the `Symfony\Component\HtmlRenderer` types directly. The bridge owns terminal widgets and adapters; it does not duplicate or alias these engine contracts.
