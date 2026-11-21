TUI Component
=============

The TUI component provides a terminal UI framework for building rich,
interactive CLI applications in PHP.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Getting Started
---------------

```bash
composer require symfony/tui
```

```php
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

$tui = new Tui(new StyleSheet()->addRule('.title', new Style()->withColor('green')));

$title = new TextWidget('What is your name?');
$title->addStyleClass('title');
$name = new InputWidget();
$name->onSubmit(static function (SubmitEvent $event) use ($title): void {
    $title->setText('Hello '.$event->getValue().'!');
});

$tui->add($title)->add($name);
$tui->run();
```

F6 and Shift+F6 move the focus between widgets. The intl extension enables drawing right-to-left text, like Arabic
and Hebrew, in visual order.

Rendering backends
------------------

Tui draws its widget tree with a renderer, `Symfony\Component\Tui\Render\Renderer` by default. Another backend
implements `RendererInterface` (styles, layout, frames) and is selected with `Tui::setRenderer()` before the TUI
starts; its `FrameWriterInterface` writes the frames to the terminal, and implementing `FocusPolicyInterface` too
decides which widgets take the focus and in what order. Widgets keep working with any backend: a renderer keeps what it
knows of each widget with `AbstractWidget::getExtensionState()`, matches custom states set with
`AbstractWidget::setState()`, and listens to `RendererChangeEvent` when another renderer is selected.
The TuiHtml component (`symfony/tui-html`) is such a backend, drawing HTML and CSS; applications using the native
widgets need neither it nor anything it depends on.

The lifecycle of a renderer:

 1. `Tui::setRenderer()`, before `Tui::start()`: the renderer creates its frame writer (`createFrameWriter()`) and gets
    the style sheets added so far (`addStyleSheet()`); if either fails, the previous renderer stays in place. The
    render caches of the widgets are cleared, `RendererChangeEvent` is dispatched, and the frame writer of the
    previous renderer is closed.
 2. Each frame: `renderFrame()` draws the tree, reusing the render of each unchanged widget as it likes
    (`AbstractWidget::getRenderCache()`), and the frame writer writes the lines; the focused widget is then asked again
    to the focus policy (`FocusPolicyInterface::allowsFocus()`). A widget that draws others, like a tab panel, has
    them drawn with `renderWidgetLines()`, through `WidgetContext::renderWidget()`.
 3. Between frames: `resolveStyle()` and `getWidgetRect()` answer widgets and pointer events, `invalidateStyles()`
    is called once for each change of the tree, and `orderFocusCycle()` on each focus move.
 4. `Tui::stop()`: the frame writer is closed (`FrameWriterInterface::close()`), and the terminal given back even
    when that fails.

A renderer can also decorate the native one. Styles still come from style sheets, with state selectors for custom
states; a decorator adds what the native renderer does not decide, like which widgets take the focus:

```php
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\FrameWriterInterface;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;

/**
 * Draws like the native renderer, and keeps the widgets with the "muted" state out of the focus cycle, like hidden ones.
 */
final class MutingRenderer implements RendererInterface, FocusPolicyInterface
{
    public function __construct(private readonly RendererInterface $inner = new Renderer())
    {
    }

    public function allowsFocus(AbstractWidget $widget): bool
    {
        if ($widget->hasState('muted')) {
            return false;
        }
        // What Tui checks without a policy: neither the widget nor a parent is hidden
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($this->inner->resolveStyle($current)->getHidden()) {
                return false;
            }
        }

        return true;
    }

    public function orderFocusCycle(array $widgets): array
    {
        return $widgets;
    }

    public function resolveStyle(AbstractWidget $widget): Style { return $this->inner->resolveStyle($widget); }
    public function addStyleSheet(StyleSheet $styleSheet): void { $this->inner->addStyleSheet($styleSheet); }
    public function resolveElement(AbstractWidget $widget, string $element): Style { return $this->inner->resolveElement($widget, $element); }
    public function invalidateStyles(): void { $this->inner->invalidateStyles(); }
    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface { return $this->inner->createFrameWriter($terminal); }
    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect { return $this->inner->getWidgetRect($widget); }
    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface { return $this->inner->renderFrame($root, $columns, $rows); }
    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface { return $this->inner->renderWidgetLines($widget, $context); }
}

$tui = new Tui(new StyleSheet([':muted' => new Style(dim: true)]), renderer: new MutingRenderer());
$tui->add(new InputWidget())->add((new InputWidget())->setState('muted'));
$tui->run();
```

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Documentation](https://symfony.com/doc/current/tui.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
