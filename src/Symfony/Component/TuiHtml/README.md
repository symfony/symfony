TuiHtml Component
=================

The TuiHtml component builds Symfony Tui widget trees from HTML and styles them with CSS.
It requires PHP 8.4.1 or later and the DOM extension. GD enables image rendering; Intl improves bidirectional text rendering.

Installation
------------

```bash
composer require symfony/tui-html
```

Select the HTML renderer before starting Tui, then mount the view:

```php
use Symfony\Component\Tui\Tui;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\Renderer as HtmlRenderer;

$tui = new Tui();
$tui->setRenderer(new HtmlRenderer());

$view = (new HtmlBuilder())->build('<style>h1 { color: green }</style><h1>Hello</h1><input id="name" aria-label="Name">');
$view->mount($tui);
$tui->run();
```

Mounting requires an explicitly selected HTML renderer. The renderer cannot be replaced after Tui has started, including after stopping it.
Native Tui widgets and native stylesheets can be used with the HTML renderer; custom widgets keep Tui's `RenderContext` and `Style` contracts.
CSS computed styles, layout, SVG rendering, hyphenation resources, and HTML controls belong to TuiHtml.

Configure media preferences on the HTML renderer with `setMediaContext()`.
Use its `getStyleSheet()->addProvider()` for additional HTML style providers.
The HTML view provides selectors for finding widgets, event wiring, form handling, fragment navigation, and pointer interactions.

Twig templates
--------------

The optional `Symfony\Component\TuiHtml\Twig\TuiRenderer` builds views from Twig templates and blocks.
Install `twig/twig` to use it. Symfony UX TwigComponent can render reusable HTML components before the HTML builder processes them.
`Symfony\Component\TuiHtml\Twig\TuiExtension` supplies the `condensed` test for density-dependent markup.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/index.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues)
