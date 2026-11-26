<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Event\PressEvent;
use Symfony\Bridge\TuiHtml\Exception\LogicException;
use Symfony\Bridge\TuiHtml\Html\Element\ImageElement;
use Symfony\Bridge\TuiHtml\Html\Element\TextElement;
use Symfony\Bridge\TuiHtml\Internal\ContainerState;
use Symfony\Bridge\TuiHtml\Style\DefaultStyleSheet;
use Symfony\Bridge\TuiHtml\Style\RasterImage;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Style\VectorImage;
use Symfony\Bridge\TuiHtml\Widget\ButtonWidget;
use Symfony\Bridge\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Bridge\TuiHtml\Widget\CompositeControlInterface;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\DropdownWidget;
use Symfony\Bridge\TuiHtml\Widget\HtmlRootWidget;
use Symfony\Bridge\TuiHtml\Widget\LinkedTextWidget;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineNode;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;
use Symfony\Component\HtmlRenderer\Html\IdAttributes;
use Symfony\Component\HtmlRenderer\Html\ShadowRoots;
use Symfony\Component\HtmlRenderer\Vector\SvgNames;
use Symfony\Component\Tui\Exception\InvalidArgumentException as TuiInvalidArgumentException;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\Tui\Widget\WidgetContainerInterface;

/**
 * Builds a widget tree from HTML.
 *
 * Elements are mapped to widgets by the {@see ElementRegistry}; <style> and
 * <link rel="stylesheet"> feed the {@see CssStyleSheet}, which also learns
 * which element each widget stands for, so selectors match the markup.
 *
 * Unknown elements build their children in a container and are reported,
 * like unknown attributes are kept for attribute selectors: HTML is
 * forgiving, and so is the builder.
 *
 * @experimental
 */
final class HtmlBuilder
{
    /** Elements that never produce a widget */
    /** The elements of HTML that are blocks: inside an inline one, like a <div> in a link, it is a block too */
    private const BLOCKS = ['address', 'article', 'aside', 'blockquote', 'center', 'details', 'dialog', 'dd', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'li', 'listing', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'search', 'section', 'table', 'ul', 'xmp'];
    private const SKIPPED = ['head', 'title', 'meta', 'script', 'template', 'style', 'link', 'base', 'datalist', 'area', 'track', 'param'];
    /** Whether scripts run, like with a script processor: <noscript> is then not drawn, see setScripting() */
    private bool $scripting = false;
    /** Whether the default styles of documents apply, see setDefaultStyles() */
    private bool $defaultStyles = true;
    /** Inline elements: their content is text of the enclosing block */
    public const INLINE = ['a', 'abbr', 'span', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'i', 'ins', 'kbd', 'mark', 'q', 's', 'samp', 'small', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr', 'ruby', 'rb', 'rt', 'rtc', 'rp', 'font', 'big', 'tt', 'acronym', 'nobr', 'strike', ...self::MATHML];
    /** MathML, drawn as linear text, like x^2 and a/b (see the default stylesheet) */
    public const MATHML = ['math', 'mi', 'mn', 'mo', 'ms', 'mtext', 'mspace', 'mrow', 'mstyle', 'mpadded', 'mphantom', 'merror', 'menclose', 'msup', 'msub', 'msubsup', 'mfrac', 'msqrt', 'mroot', 'munder', 'mover', 'munderover', 'mmultiscripts', 'mprescripts', 'semantics', 'annotation', 'annotation-xml', 'mtable', 'mtr', 'mtd'];
    private readonly ElementRegistry $registry;
    /** @var array<int, array{form: \Dom\Element, controls: list<array{AbstractWidget, \Dom\Element, list<\Closure(): void>}>}> Each <form> element not built yet, by its object id, and the controls built before it: the element is held, so that the id stays its own */
    private array $formControls = [];
    /** @var array<int, Element\FormBinding> The forms built, by their element */
    private array $formBindings = [];
    private readonly TextElement $inlineText;
    /** @var \SplObjectStorage<\Dom\Element, null> The links laid out as blocks a text block of which takes the focus */
    private \SplObjectStorage $linkBlocks;
    private bool $userAgentStylesLoaded = false;
    /** The URL of the <base href> of the document being built */
    private ?string $baseUrl = null;
    /** @var array<string, true> */
    private array $reported = [];
    private ?HtmlView $view = null;
    /** @var array<int, array{\Dom\Element, AbstractWidget}> The widgets setInnerHtml() keeps, of the elements outside the content replaced that are built again with it, by the object id of their element, see buildFragment() */
    private array $kept = [];
    /** @var array<int, \Dom\Element> Those of them built so far, by the object id of their element */
    private array $keptBuilt = [];
    /** Whether forms around the kept widgets are built again, their controls to bind again */
    private bool $rebindKept = false;
    /** What buildFragment() looks up across the document, see HtmlView::setInnerHtml() */
    private ?DocumentIndex $index = null;
    /** @var array<int, Element\FormBinding> The forms buildFragment() bound controls to, by their object id: put in the order of the document */
    private array $touched = [];
    /** @var list<string> The ids of the forms buildFragment() built, around what it replaced or in it: their controls outside bound to them again */
    private array $formIds = [];
    /** @var array<string, string> Label text by the id of the element it labels */
    private array $labels = [];
    private int $scopes = 0;
    /** @var \WeakMap<\Dom\Element, true> The <style> and <link> elements read, see loadStyles(): one edited in place outside HtmlView::setInnerHtml() is not read again */
    private \WeakMap $read;
    /** @var list<array{RasterImage|VectorImage, int, string}> The images drawn in the text of the inline nodes read last, see getInlineNodes() */
    private array $inlineImages = [];
    private int $inlineDepth = 0;
    /** The documents nested in each other at most, like <iframe srcdoc> in <iframe srcdoc> */
    private const MAX_FRAME_DEPTH = 3;

    /**
     * The deepest an element is built, <html> at 1, like the 512 of the HTML parser of Chromium: deeper elements are
     * siblings of their ancestor at this depth, or 20,000 nested elements would lay out out of memory.
     */
    public const MAX_DEPTH = 512;
    /**
     * The largest document built by default, see the $maxBytes argument of the constructor: building takes about 65 MB
     * of memory for each MB of HTML, before it is drawn.
     */
    public const MAX_BYTES = 4 * 1024 * 1024;
    private static int $frameDepth = 0;

    /**
     * @param int $maxBytes The largest document build() accepts, in bytes
     */
    private bool $useHtmlRenderer = false;

    public function __construct(private readonly CssStyleSheet $styleSheet = new CssStyleSheet(), ?ElementRegistry $registry = null, private readonly ?LoggerInterface $logger = null, private readonly int $maxBytes = self::MAX_BYTES)
    {
        $this->read = new \WeakMap();
        $this->registry = $registry ?? new ElementRegistry();
        $this->inlineText = new TextElement();
        $this->linkBlocks = new \SplObjectStorage();
    }

    public function getStyleSheet(): CssStyleSheet
    {
        return $this->styleSheet;
    }

    /**
     * Builds a document nested in the one being built, like the srcdoc of an <iframe>: its own stylesheet, its own
     * view, mounted with the view of the page; null past a few levels of nesting.
     */
    public function buildFrame(string $html): ?HtmlView
    {
        if (self::$frameDepth >= self::MAX_FRAME_DEPTH) {
            return null;
        }
        ++self::$frameDepth;
        try {
            $frame = (new self($this->styleSheet->createNested(), $this->registry, $this->logger, $this->maxBytes))->useHtmlRenderer($this->useHtmlRenderer)->build($html);
        } finally {
            --self::$frameDepth;
        }
        $this->view?->addFrame($frame);

        return $frame;
    }

    /**
     * The URL the relative links of the document are resolved against, like the href of its <base>.
     *
     * @internal
     */
    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function getRegistry(): ElementRegistry
    {
        return $this->registry;
    }

    /**
     * Sets whether scripts run, like with a script processor: <noscript> and its style sheets are then left out, like
     * browsers do. Scripts do not run by default: the content of <noscript> is drawn, like in a browser without them.
     */
    public function setScripting(bool $scripting): static
    {
        $this->scripting = $scripting;

        return $this;
    }

    /**
     * Sets whether the default styles of documents apply, after the default style sheets of browsers, like a line between
     * paragraphs (see Resources/default.css); without them, an interface laid out by its own rules is not spaced like a
     * document. They apply by default.
     */
    public function setDefaultStyles(bool $defaultStyles): static
    {
        if ($this->userAgentStylesLoaded && $defaultStyles !== $this->defaultStyles) {
            throw new LogicException('The default styles are set before the first document is built.');
        }
        $this->defaultStyles = $defaultStyles;

        return $this;
    }

    /**
     * Builds the widget tree of an HTML document or fragment.
     *
     * @throws InvalidArgumentException When the document is larger than the $maxBytes given to the constructor
     */
    /**
     * Opts into the experimental core rendering path while retaining native widget handles.
     *
     * @experimental This migration path does not yet preserve every compatibility feature.
     */
    public function useHtmlRenderer(bool $enabled = true): static
    {
        $this->useHtmlRenderer = $enabled;

        return $this;
    }

    public function build(string $html): HtmlView
    {
        if (\strlen($html) > $this->maxBytes) {
            throw new InvalidArgumentException(\sprintf('The HTML document has %d bytes, more than the %d an HtmlBuilder accepts.', \strlen($html), $this->maxBytes));
        }
        if (!$this->userAgentStylesLoaded) {
            $this->styleSheet->addUserAgentStyles(file_get_contents(__DIR__.'/Resources/ua.css') ?: throw new LogicException('The user agent stylesheet cannot be read.'));
            if ($this->useHtmlRenderer) {
                $this->styleSheet->addUserAgentStyles('table { border-spacing: var(--tui-cell-spacing, 1ch) 0 } ::scroll-button(*):disabled { opacity: .5 } ::scroll-marker-group { display: flex; column-gap: 1ch }');
            }
            if ($this->defaultStyles) {
                $this->styleSheet->addUserAgentStyles(file_get_contents(__DIR__.'/Resources/default.css') ?: throw new LogicException('The default stylesheet cannot be read.'));
            }
            $this->userAgentStylesLoaded = true;
        }
        [$html, $selectTag] = LegacyParser::prepare($html);
        $document = \Dom\HTMLDocument::createFromString(preg_match('/^\s*(<!doctype|<html)/i', $html) ? $html : '<!DOCTYPE html><html><body>'.$html.'</body></html>', \LIBXML_NOERROR);
        if (\PHP_VERSION_ID < 80415 && null !== $document->documentElement) {
            self::markIds($document->documentElement);
        }
        // A document without a doctype, or with an old one, is in quirks mode, like an old page laid out in tables: the
        // user agent style sheet keeps some of its rules for them, like tables not inheriting the text-align of a
        // <center> around them
        if (self::isQuirksMode($document)) {
            $document->documentElement?->setAttribute('tui-quirks', '');
        }
        try {
            $depths = $this->composeShadowRoots($document);
            LegacyParser::restore($document, $selectTag);
            // After the parser added elements of its own, like those it reopens, and the shadow trees were composed
            if (null !== $document->documentElement) {
                $this->limitDepth($document->documentElement);
            }
            foreach ($document->getElementsByTagName('svg') as $svg) {
                SvgNames::normalize($svg);
            }
            $this->loadStyles($document, $depths);
            $this->collectLabels($document);
            // The first <base href>, when it is absolute: there is no document URL to resolve a relative one against
            $this->baseUrl = null;
            $base = $document->querySelector('base[href]');
            if (null !== $base && preg_match('~^[a-z][a-z0-9+.-]*:~i', $href = trim((string) $base->getAttribute('href')))) {
                $this->baseUrl = $href;
            }
            // The links followed are known by their address resolved against it, which :visited matches
            $this->styleSheet->setBaseUrl($this->baseUrl);
            $body = $document->body ?? throw new InvalidArgumentException('The HTML document has no body.');
            $this->view = new HtmlView($this->styleSheet);
            $this->linkBlocks = new \SplObjectStorage();
            $this->formControls = [];
            $this->formBindings = [];
            $children = $this->buildChildWidgets($body);
            // <html> and <body> are elements too: their rules and their attributes, like lang and dir, apply
            $bodyWidget = ContainerState::of(new ContainerWidget())->setPassesDefiniteHeight();
            foreach ($children as $child) {
                $bodyWidget->add($child);
            }
            $this->styleSheet->describe($bodyWidget, 'body', $this->attributesOf($body));
            $this->view->setElement($bodyWidget, $body);
            $root = ContainerState::of($this->useHtmlRenderer ? new HtmlRootWidget($this->styleSheet) : new ContainerWidget())->setPassesDefiniteHeight();
            $root->add($bodyWidget);
            $htmlAttributes = $this->attributesOf($document->documentElement);
            // The first valid <meta name="color-scheme">: the color schemes of the page, under any CSS rule
            foreach ($document->querySelectorAll('meta[name="color-scheme" i][content]') as $meta) {
                if (CssStyleSheet::isColorScheme($content = (string) $meta->getAttribute('content'))) {
                    $htmlAttributes[CssStyleSheet::COLOR_SCHEME_ATTRIBUTE] = $content;
                    break;
                }
            }
            $this->styleSheet->describe($root, 'html', $htmlAttributes);
            $view = $this->view;
            // What setInnerHtml() builds more of the document with: the document, the base URL and the forms
            $view->keepSource($document, $this, $this->baseUrl, $this->formBindings);
        } finally {
            // What was known of the document while it was built, not to hold it once built, nor when its build failed
            $this->view = null;
            $this->labels = [];
            $this->formControls = [];
            $this->formBindings = [];
            $this->linkBlocks = new \SplObjectStorage();
            $this->inlineImages = [];
            // Not those of a page of the style sheet mounted still
            HtmlView::releaseDocumentsOf($this->styleSheet);
        }
        $view->setRoot($root);
        // Whitespace stripped and collapsed, like document.title
        $view->setDocumentTitle($document->title);

        return $view;
    }

    /**
     * Builds an element and applies its global attributes.
     *
     * Factories call it for the children they own.
     */
    public function buildElement(\Dom\Element $element): ?AbstractWidget
    {
        // An element outside the content setInnerHtml() replaced: its widget as it is, its state and listeners with it,
        // like the element a browser keeps
        if ($this->kept && null !== $kept = $this->kept[spl_object_id($element)] ?? null) {
            unset($this->kept[spl_object_id($element)]);
            $this->keptBuilt[spl_object_id($element)] = $element;
            $this->styleSheet->forgetItem($kept[1]);
            if ($this->rebindKept) {
                $this->bindKept($element);
            }

            return $kept[1];
        }
        $tagName = strtolower($element->localName);
        if ($this->isSkipped($tagName)) {
            return null;
        }
        $factory = $this->registry->get($tagName);
        // An inline element holding blocks, like a link around a card: a block of its blocks and its text
        if (null === $factory && \in_array($tagName, self::INLINE, true)) {
            $factory = $this->inlineText;
        }
        if (null === $factory) {
            $this->report('html.elements.'.$tagName, \sprintf('The <%s> element is not supported: its children are built in a container.', $tagName));
            $factory = $this->registry->get('div');
        }
        try {
            $widget = $factory->create($element, $this);
        } catch (TuiInvalidArgumentException|InvalidArgumentException $e) {
            // Like a browser, render what can be, and say what could not; native widgets throw the exceptions of Tui
            $this->report('html.elements.'.$tagName.':'.$e->getMessage(), $e->getMessage());
            $widget = new ContainerWidget();
        }
        // The element is the control of a composite one, like the input of a combobox, its other parts anonymous
        $this->applyAttributes($widget instanceof CompositeControlInterface ? $widget->getControl() : $widget, $element, $tagName);
        $this->view?->setElement($widget, $element);

        return $widget;
    }

    /**
     * Parses $html into an element of a document built already, like innerHTML: the fragment parsing algorithm with
     * the element as its context. The style sheets of the content replaced stop applying. Returns what that content
     * could name outside it, for buildFragment().
     *
     * @internal for {@see HtmlView::setInnerHtml()}
     *
     * @return array{array<string, true>, array<string, true>, array<string, true>}
     */
    public function parseFragment(\Dom\Element $element, string $html, ?DocumentIndex $index = null): array
    {
        if (\strlen($html) > $this->maxBytes) {
            throw new InvalidArgumentException(\sprintf('The HTML fragment has %d bytes, more than the %d an HtmlBuilder accepts.', \strlen($html), $this->maxBytes));
        }
        [$html, $selectTag] = LegacyParser::prepare($html);
        $document = $element->ownerDocument;
        \assert($document instanceof \Dom\HTMLDocument);
        $before = self::namers($element);
        // The style sheets of the content replaced stop applying, like their elements leaving the document
        foreach ($element->querySelectorAll('style, link') as $style) {
            $this->styleSheet->removeSourcesOf($style);
        }
        $index?->remove($element);
        // The fragment parsing algorithm, the element the context: what is not allowed there is dropped or moved, and
        // no end tag of the fragment closes an element around it; declarative shadow roots are not attached, like innerHTML
        $element->innerHTML = $html;
        if (\PHP_VERSION_ID < 80415) {
            self::markIds($element);
        }
        LegacyParser::restore($document, $selectTag);
        $depth = 1;
        for ($ancestor = $element->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            ++$depth;
        }
        $this->limitDepth($element, $depth);
        foreach ($element->getElementsByTagName('svg') as $svg) {
            SvgNames::normalize($svg);
        }
        $index?->add($element);

        return $before;
    }

    /**
     * Adds the style sheets in an element parseFragment() filled to those of the document, before it is built, for
     * the elements outside it they may build otherwise.
     *
     * @internal for {@see HtmlView::setInnerHtml()}
     */
    public function loadStylesOf(\Dom\Element $element): void
    {
        $this->loadStyles($element);
    }

    /**
     * What the parent of an element decides from its content, like whether it is in the line of the text around it,
     * a control of that line, or a block: null when its content cannot change that, like for a <div> or a <p>.
     *
     * @internal for {@see HtmlView::setInnerHtml()}
     */
    public function placement(\Dom\Element $element): ?string
    {
        $tagName = strtolower($element->localName);
        // A block whose rules display it as a block in every media and state, like a <div> or a <p>: a line of its own,
        // whatever it holds; any other element, like a <span>, a <label> or a <div> displayed inline-block, is in the
        // line of the text around it or not by whether it holds a block or a control, and by rules like :has()
        if (\in_array($tagName, self::BLOCKS, true) && !$this->styleSheet->mayBeInline($element) && !$this->styleSheet->mayBeInlineLevel($element) && !$this->styleSheet->staysInline($element) && !$this->styleSheet->staysInlineLevel($element)) {
            return null;
        }

        return implode(',', array_map(intval(...), [$this->isInline($element), $this->isInlineControl($element), $this->holdsBlock($element), $this->holdsBlockInFlow($element), $this->holdsControl($element), $this->styleSheet->mayBeBlock($element), $this->styleSheet->mayBeInline($element), $this->styleSheet->mayBeInlineLevel($element), $this->styleSheet->staysInlineLevel($element)]));
    }

    /**
     * Builds an element of a document built already, after parseFragment() replaced the content of $parsed, in it or
     * itself: into $view, with its base URL and its forms, the document-wide state left as it is. Names again what
     * the content replaced or added names outside, and binds the controls outside whose form it added or removed.
     * Returns the widgets of the children of the <body>, else the element built again.
     *
     * @internal for {@see HtmlView::setInnerHtml()}
     *
     * @param array{array<string, true>, array<string, true>, array<string, true>} $before     What parseFragment() returned
     * @param array<int, array{\Dom\Element, AbstractWidget}>                      $kept       The widgets of the elements outside $parsed under
     *                                                                                         $element, by the object id of their element: kept
     *                                                                                         as they are; those not built again are left in it
     * @param bool                                                                 $rebindKept Whether forms around them are built again
     *
     * @return AbstractWidget|list<AbstractWidget>
     */
    public function buildFragment(HtmlView $view, \Dom\Element $element, \Dom\Element $parsed, array $before, array &$kept = [], bool $rebindKept = false): AbstractWidget|array
    {
        $document = $element->ownerDocument;
        \assert($document instanceof \Dom\HTMLDocument);
        $this->kept = $kept;
        $this->rebindKept = $rebindKept;
        $this->index = $view->getDocumentIndex();
        try {
            // Its style sheets are added to those of the document: a source read already is not added again
            $this->loadStyles($element);
            // The labels: those of what is built, the others found by the id of the control they name, see labelOf()
            $this->collectLabels($element);
            [$ids, $labelled, $around] = self::namers($parsed);
            $ids = array_map(strval(...), array_keys($ids + $before[0]));
            $around = array_map(strval(...), array_keys($around));
            // A <label for> outside naming an element removed names none now
            foreach (array_keys($before[0]) as $id) {
                if (null === $document->getElementById((string) $id)) {
                    foreach ($this->index->labelsFor((string) $id) as $label) {
                        $this->report('a11y:label:'.$id, \sprintf('The <label for="%s"> does not label any element.', $id));
                    }
                }
            }
            [$this->baseUrl, $this->formBindings] = $view->getSourceState();
            $this->view = $view;
            $this->linkBlocks = new \SplObjectStorage();
            $built = 'body' === strtolower($element->localName) ? $this->buildChildWidgets($element) : $this->buildElement($element);
            $this->renameOutside($view, $element, [...$ids, ...$around], array_keys($labelled + $before[1]));
            // The form of a control is the first element of the id of its form attribute: changed by those added or removed
            $this->rebindOutside($view, $element, [...$ids, ...$this->formIds]);
            // The values of the forms are submitted in the order of the document
            foreach ($this->touched as $binding) {
                $binding->sort();
            }
            $view->keepSource($document, $this, $this->baseUrl, $this->formBindings);
        } finally {
            $kept = $this->kept;
            $this->kept = $this->keptBuilt = $this->touched = $this->formIds = [];
            $this->rebindKept = false;
            $this->index = null;
            $this->view = null;
            $this->labels = [];
            $this->formControls = [];
            $this->formBindings = [];
            $this->linkBlocks = new \SplObjectStorage();
            $this->inlineImages = [];
            HtmlView::releaseDocumentsOf($this->styleSheet);
        }

        return $built ?? [];
    }

    /**
     * Names again the elements outside what was built that the content replaced or added names, like a browser
     * computing names from the document as it is: by a <label> (for, or around them), aria-labelledby and
     * aria-describedby (the description of a <details>).
     *
     * @param list<string>     $ids      The ids in the content replaced or added, and of the element and its ancestors
     * @param list<string|int> $labelled The ids the labels in that content or around it name
     */
    private function renameOutside(HtmlView $view, \Dom\Element $built, array $ids, array $labelled): void
    {
        $document = $built->ownerDocument;
        $elements = [];
        foreach ($labelled as $id) {
            if (null !== $control = $document->getElementById((string) $id)) {
                $elements[] = $control;
            }
        }
        array_push($elements, ...$this->index?->referencing($ids) ?? []);
        foreach ($elements as $element) {
            if ($this->isBuilt($built, $element) || null === $widget = $view->getWidget($element)) {
                continue;
            }
            $named = $widget instanceof CompositeControlInterface ? $widget->getControl() : $widget;
            if ($view->hasOwnName($named)) {
                // Without a name from the page, the one it gave itself, like when it was built
                $label = $this->accessibleName($element, $named, $this->attributesOf($element), $view->getOwnName($named)) ?? $view->getOwnName($named);
                $named->setLabel($label);
                if ($named instanceof DropdownWidget) {
                    $named->getField()->setLabel($label);
                }
            }
            if ($named instanceof CollapsibleWidget && null !== $id = $element->getAttribute('aria-describedby')) {
                $described = $document->getElementById($id);
                $named->setDescription(null !== $described ? trim($this->getText($described)) : null);
            }
        }
    }

    /**
     * Whether an element has a widget built by buildFragment(): in what it built, not in a widget it kept.
     */
    private function isBuilt(\Dom\Element $built, \Dom\Element $element): bool
    {
        if (!$built->contains($element)) {
            return false;
        }
        if ($this->keptBuilt) {
            for ($ancestor = $element; null !== $ancestor && $ancestor !== $built; $ancestor = $ancestor->parentElement) {
                if (isset($this->keptBuilt[spl_object_id($ancestor)])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Binds the controls of a widget kept by buildFragment() to their form built again, like the form owner of a
     * control that stays in the document: reset to their defaults as built.
     */
    private function bindKept(\Dom\Element $element): void
    {
        $view = $this->view;
        if (null === $view) {
            return;
        }
        $controls = iterator_to_array($element->querySelectorAll('input, select, textarea, button'), false);
        if (\in_array(strtolower($element->localName), ['input', 'select', 'textarea', 'button'], true)) {
            $controls[] = $element;
        }
        foreach ($controls as $control) {
            if (null === $widget = $view->getWidget($control)) {
                continue;
            }
            $widget = $widget instanceof CompositeControlInterface ? $widget->getControl() : $widget;
            $form = self::formOwner($control);
            if (null !== $form && null !== $defaults = $view->getFormDefaults($widget)) {
                if (!($this->formBindings[spl_object_id($form)] ?? null)?->has($widget)) {
                    $this->bindControl($widget, $control, $form, $defaults);
                }
            }
        }
    }

    /**
     * Binds the controls outside what was built whose form attribute names a form it added, unbinds those of a form it
     * removed, like the form owner of a control the document changed around.
     *
     * @param list<string> $ids The ids in the content replaced or added, and of the element and its ancestors
     */
    private function rebindOutside(HtmlView $view, \Dom\Element $built, array $ids): void
    {
        // Those whose form attribute names an id the content replaced or added had or has: their form may be another
        foreach ($this->index?->controlsOf($ids) ?? [] as $control) {
            if ($this->isBuilt($built, $control) || null === $widget = $view->getWidget($control)) {
                continue;
            }
            $widget = $widget instanceof CompositeControlInterface ? $widget->getControl() : $widget;
            $form = self::formOwner($control);
            $binding = null !== $form ? $this->formBindings[spl_object_id($form)] ?? null : null;
            $owner = Element\FormBinding::of($widget);
            if (null !== $owner && $owner !== $binding) {
                $owner->remove($widget);
            }
            if (null !== $binding && !$binding->has($widget)) {
                // Reset to its default as built, not to what the user changed it to before the splice
                $this->addControl($binding, $widget, $control, $view->getFormDefaults($widget));
            }
        }
    }

    /**
     * Builds the children of an element into a container widget.
     *
     * Text between child elements becomes a <tui-text>, so that mixed
     * content keeps its order.
     */
    public function buildChildren(\Dom\Element $element, WidgetContainerInterface $container): void
    {
        foreach ($this->buildChildWidgets($element) as $child) {
            $container->add($child);
        }
    }

    /**
     * Returns the plain text of an element, whitespace collapsed like in HTML
     * unless $preserveWhitespace is true.
     *
     * Use it for text that widgets style themselves, like option labels; text
     * blocks keep their inline elements, see getInlineNodes().
     */
    public function getText(\Dom\Node $node, bool $preserveWhitespace = false): string
    {
        $text = '';
        foreach ($this->getInlineNodes($node) as $inline) {
            $text .= $inline->getPlainText();
        }
        $text = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text));

        return $preserveWhitespace ? $text : preg_replace('/[ \t\r\n\f]+/', ' ', $text) ?? $text;
    }

    /**
     * Returns the inline content of an element: text runs and inline elements.
     *
     * Text is data: control bytes are stripped when the widget renders it.
     *
     * @return list<InlineNode>
     */
    public function getInlineNodes(\Dom\Node $node, bool $images = false): array
    {
        // The images of the text read from here, when it draws them, see takeInlineImages()
        if (0 === $this->inlineDepth) {
            $this->inlineImages = [];
        }
        ++$this->inlineDepth;
        try {
            $nodes = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \Dom\Text) {
                    // The characters that stand for the cells of images are not text
                    $nodes[] = InlineNode::text(preg_replace('/[\x{100000}-\x{10FFFF}]/u', '', $child->data) ?? $child->data);
                } elseif ($child instanceof \Dom\Element && !$this->isSkipped($tagName = strtolower($child->localName))) {
                    $factory = $this->registry->get($tagName);
                    // An image in a link: drawn in the line, a row tall, a character standing for each of its cells
                    if ($images && $factory instanceof ImageElement && \count($this->inlineImages) < RichTextWidget::MAX_INLINE_IMAGES && null !== $image = $factory->inlineImage($child, $this)) {
                        $index = \count($this->inlineImages);
                        $this->inlineImages[] = [...$image, $factory->textAlternative($child, $this, true) ?? ''];
                        $nodes[] = InlineNode::element($tagName, $this->attributesOf($child), [InlineNode::text(RichTextWidget::inlineImageText($index, $image[1]))]);
                        continue;
                    }
                    // Other embedded content: its text alternative
                    $alternative = $factory instanceof ImageElement ? $factory->textAlternative($child, $this, true) : null;
                    // None, like a thumbnail of Wikipedia not loaded: nothing, not its border around no text
                    if ('' === $alternative) {
                        continue;
                    }
                    $nodes[] = InlineNode::element($tagName, $this->attributesOf($child), null === $alternative ? $this->getInlineNodes($child, $images) : [InlineNode::text($alternative)]);
                }
            }
        } finally {
            --$this->inlineDepth;
        }

        return $nodes;
    }

    /**
     * The images drawn in the text of the inline nodes read last, by the characters that stand for their cells, see
     * RichTextWidget::setInlineImages(); taken once.
     *
     * @return list<array{RasterImage|VectorImage, int, string}>
     */
    public function takeInlineImages(): array
    {
        [$images, $this->inlineImages] = [$this->inlineImages, []];

        return $images;
    }

    /**
     * Whether an element is inline, in the line of the text around it: those of HTML that are, and a custom element,
     * like <x-badge>, inline like any element by default, unless it holds blocks, those of its shadow tree for a host.
     */
    public function isInline(\Dom\Element $element): bool
    {
        $tagName = strtolower($element->localName);
        if (\in_array($tagName, self::INLINE, true)) {
            // One holding a form control, like the <span> Contact Form 7 wraps each field in: a control of the line;
            // one holding a block, like a link around a card: a block, its text before and after the block on lines
            // of their own, like CSS
            return !$this->holdsControl($element) && !$this->holdsBlock($element);
        }
        // A block displayed inline by the rules of every media and state, like the <li> of a breadcrumb: in the line,
        // unless it holds a block or a control
        if (\in_array($tagName, self::BLOCKS, true) && !\in_array($tagName, ['table', 'hr', 'details', 'dialog', 'fieldset', 'form'], true) && $this->styleSheet->staysInline($element) && !$this->holdsBlock($element) && !$this->holdsControl($element)) {
            return true;
        }
        // A block displayed inline-block holding text only, like the <li> of the categories of Wikipedia: in the line,
        // like an inline element displayed so, unless boxed
        if (\in_array($tagName, self::BLOCKS, true) && !\in_array($tagName, ['table', 'hr', 'details', 'dialog', 'fieldset', 'form'], true) && $this->styleSheet->staysInlineLevel($element) && !$this->holdsBlock($element) && !$this->holdsControl($element) && !$this->styleSheet->mayLayOutItems($element) && !$this->styleSheet->mayBeInlineBox($element) && !$this->styleSheet->mayBeOutOfFlow($element) && !$this->styleSheet->mayFloat($element) && !$this->isLonePaddedInlineBlock($element)) {
            return true;
        }
        // A slot, display: contents, is in the line when what it holds is
        if ('slot' === $tagName) {
            foreach ($element->childNodes as $child) {
                if ($child instanceof \Dom\Element && !$this->isSkipped(strtolower($child->localName)) && !$this->isInline($child) && !$this->isTextAlternative($child)) {
                    return false;
                }
            }

            return true;
        }
        if (!preg_match('/^[a-z][^\s\/>]*-[^\s\/>]*$/', $tagName) || null !== $this->registry->get($tagName)) {
            return false;
        }
        foreach ($element->childNodes as $child) {
            if ($child instanceof \Dom\Element && !$this->isSkipped(strtolower($child->localName)) && !$this->isInline($child) && !$this->isTextAlternative($child)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The link a text block is in, like the <h3> of a card in a link, the link laid out as a block; null outside one.
     *
     * @internal
     */
    public function getEnclosingLink(?\Dom\Element $element): ?\Dom\Element
    {
        for (; null !== $element; $element = $element->parentElement) {
            if ('a' === strtolower($element->localName) && '' !== trim($element->getAttribute('href') ?? '')) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Makes a text block of a link laid out as a block that link, like the title, the text and the caption of a card
     * in a link: the first one takes the focus, the others are clicked only, the link one stop of Tab, like browsers.
     *
     * @internal
     */
    public function linkBlock(LinkedTextWidget $widget, \Dom\Element $link): void
    {
        $widget->setLink(trim($link->getAttribute('href') ?? ''));
        // Its link is the <a> element around it, which setInnerHtml() keeps the focus on
        $this->view?->setTextNodes($widget, [$link]);
        if (isset($this->linkBlocks[$link])) {
            $this->view?->setTabIndex($widget, -1);
        } else {
            $this->linkBlocks[$link] = null;
        }
    }

    /**
     * Whether an inline element is an inline block padded on its sides alone in its parent, like a link of the table
     * of contents of MDN in its <li>: a box, its lines wrapped inside its padding, not text whose padding only its
     * first and last lines show.
     *
     * @internal
     */
    public function isLonePaddedInlineBlock(\Dom\Element $element): bool
    {
        $parent = $element->parentElement;
        // Its parent's ::before or ::after beside it, like the separator after a link of the breadcrumbs of Docusaurus:
        // in the line with them
        if (null === $parent || !$this->styleSheet->staysInlineLevel($element) || !$this->styleSheet->mayPadSides($element) || $this->styleSheet->mayHaveContentPseudo($parent)) {
            return false;
        }
        foreach ($parent->childNodes as $sibling) {
            if ($sibling !== $element && !$sibling instanceof \Dom\Comment && !($sibling instanceof \Dom\Text && '' === trim($sibling->textContent ?? ''))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether an element is embedded content drawn as its text alternative in the line of the text around it, like
     * an <img> that cannot be drawn or an <svg>, unless it is displayed as a block or placed out of the flow.
     */
    public function isTextAlternative(\Dom\Element $element): bool
    {
        $factory = $this->registry->get(strtolower($element->localName));

        return $factory instanceof ImageElement && null !== $factory->textAlternative($element, $this) && !$this->styleSheet->mayBeOutOfFlow($element) && !$this->styleSheet->mayBeBlock($element);
    }

    /**
     * Returns how changes of an element must be announced, if it is a live region.
     *
     * @return 'polite'|'assertive'|null
     */
    public function getLiveRegion(\Dom\Element $element): ?string
    {
        $live = $element->getAttribute('aria-live');
        $role = $element->getAttribute('role');
        if ('off' === $live) {
            return null;
        }

        return match (true) {
            'assertive' === $live, null === $live && 'alert' === $role => 'assertive',
            'polite' === $live, null === $live && ('status' === $role || 'log' === $role) => 'polite',
            default => null,
        };
    }

    /**
     * Returns an attribute value safe to display.
     */
    public function getAttributeText(\Dom\Element $element, string $name): ?string
    {
        $value = $element->getAttribute($name);

        return null === $value ? null : StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($value));
    }

    /**
     * @return array<string, string>
     */
    public function attributesOf(\Dom\Element $element): array
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }
        // dir="auto": the direction of the first strong character, like HTML, of the value of a text field
        if ('auto' === strtolower(trim($attributes['dir'] ?? ''))) {
            $text = \in_array(strtolower($element->localName), ['input', 'textarea'], true) ? $attributes['value'] ?? $element->textContent : $element->textContent;
            $attributes['dir'] = preg_match('/^[^\p{L}]*[\p{Hebrew}\p{Arabic}\p{Syriac}\p{Thaana}\p{Nko}]/u', $text ?? '') ? 'rtl' : 'ltr';
        }

        return $attributes;
    }

    /**
     * Binds the controls of a <form>: those built before it whose form attribute names it now, the others as they are
     * built.
     *
     * @internal
     */
    public function bindForm(\Dom\Element $form, Element\FormBinding $binding): void
    {
        $this->formBindings[spl_object_id($form)] = $binding;
        // One built again around the content replaced, like a <form> holding a <span> that holds a block now: the
        // binding of the one it replaces is gone, with the controls outside its form attribute bound to it
        if (null !== $this->index && null !== $id = $form->getAttribute('id')) {
            $this->formIds[] = $id;
        }
        foreach ($this->formControls[spl_object_id($form)]['controls'] ?? [] as [$widget, $control, $defaults]) {
            $this->addControl($binding, $widget, $control, $defaults);
        }
        unset($this->formControls[spl_object_id($form)]);
    }

    /**
     * Binds a control to a form: those of a document built whole are in the order of the document, which
     * setInnerHtml() puts those it adds in; those it adds are put there once it is done, see buildFragment().
     *
     * @param list<\Closure(): void>|null $defaults
     */
    private function addControl(Element\FormBinding $binding, AbstractWidget $widget, \Dom\Element $control, ?array $defaults): void
    {
        $binding->add($widget, $control, $defaults);
        if (null === $this->index) {
            $binding->markSorted();
        } else {
            $this->touched[spl_object_id($binding)] = $binding;
        }
    }

    /**
     * Binds a control to its form: now when the form is built, else when it is, see bindForm().
     *
     * @param list<\Closure(): void> $defaults
     */
    private function bindControl(AbstractWidget $widget, \Dom\Element $element, \Dom\Element $form, array $defaults): void
    {
        if (null !== $binding = $this->formBindings[spl_object_id($form)] ?? null) {
            $this->addControl($binding, $widget, $element, $defaults);
        } else {
            // Holding the element keeps its object id: a wrapper nothing holds is freed, and the form gets another
            $waiting = $this->formControls[spl_object_id($form)] ?? ['form' => $form, 'controls' => []];
            $waiting['controls'][] = [$widget, $element, $defaults];
            $this->formControls[spl_object_id($form)] = $waiting;
        }
    }

    /**
     * Tells whether a form control is disabled: by its attribute, or by a
     * <fieldset disabled> it is in, unless it is in the first <legend> of that fieldset.
     */
    public static function isDisabled(\Dom\Element $element): bool
    {
        return \Symfony\Component\HtmlRenderer\Form\ControlState::isDisabled($element);
    }

    /**
     * Marks as ids the id attributes the parser of PHP < 8.4.15 leaves unmarked, those of an id already in the document:
     * once the document changed, Document::getElementById() skips them, and finds nothing when the other element of
     * that id is removed, or that other one when it comes after them (php/php-src#20281).
     *
     * @internal
     */
    public static function markIds(\Dom\Element $root): void
    {
        IdAttributes::mark($root);
    }

    /**
     * Whether a document is in quirks mode, like HTML says: without a doctype, with another one than html, or with the
     * public identifier of an old HTML.
     */
    private static function isQuirksMode(\Dom\HTMLDocument $document): bool
    {
        $doctype = $document->doctype;
        if (null === $doctype || 'html' !== strtolower($doctype->name)) {
            return true;
        }
        $public = strtolower($doctype->publicId);

        return preg_match('~^-//(?:w3c//dtd html (?:[23]|4\.0 )|ietf//|w3o//|netscape comm|microsoft//|webtechs//|softquad|sun microsystems|o\'reilly)~', $public) || '' === $doctype->systemId && preg_match('~^-//w3c//dtd html 4\.01 (?:transitional|frameset)//~', $public);
    }

    /**
     * Whether the elements of a name are not drawn, like <script>, and <noscript> while scripts run.
     */
    private function isSkipped(string $name): bool
    {
        return \in_array($name, self::SKIPPED, true) || $this->scripting && 'noscript' === $name;
    }

    /**
     * The classes the default style sheet styles widgets of, like .h1 or .columns.
     *
     * @return array<string, true>
     */
    private static function defaultClasses(): array
    {
        static $classes = null;

        return $classes ??= array_fill_keys(array_map(static fn (string $selector) => substr($selector, 1), array_filter(array_keys(DefaultStyleSheet::create()->getRules()), static fn (string $selector) => (bool) preg_match('/^\.[\w-]+$/', $selector))), true);
    }

    /**
     * Whether an inline element holds a block of HTML, like the <div> of a card in a link, itself or in inline elements.
     */
    private function holdsBlock(\Dom\Element $element): bool
    {
        for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            $tagName = strtolower($child->localName);
            if (\in_array($tagName, self::BLOCKS, true) && !$this->isInline($child) || 'br' !== $tagName && \in_array($tagName, self::INLINE, true) && !\in_array($tagName, self::MATHML, true) && $this->holdsBlock($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an element holds a block in the flow, not one placed out of it, like the menu a link of a header opens.
     */
    private function holdsBlockInFlow(\Dom\Element $element): bool
    {
        return array_any(array_values(array_filter(iterator_to_array($element->childNodes, false), static fn (\Dom\Node $node): bool => $node instanceof \Dom\Element)), fn (\Dom\Element $child) => \in_array(strtolower($child->localName), self::BLOCKS, true) && !$this->isInline($child) && !$this->styleSheet->staysOutOfFlow($child));
    }

    /**
     * Whether an inline element holds a form control, which its text cannot; not a link, like one around a button,
     * which is followed when the button is clicked: a link of its text.
     */
    private function holdsControl(\Dom\Element $element): bool
    {
        return 'a' !== strtolower($element->localName) && null !== $element->querySelector('input:not([type="hidden" i]), select, textarea, button, meter, progress');
    }

    /**
     * @return list<AbstractWidget>
     */
    private function buildChildWidgets(\Dom\Element $element): array
    {
        $widgets = [];
        // The controls in the line of the text around them, like a <button>: see lineUpInlineControls()
        $controls = [];
        // The inline boxes with a border among them, like <a class="btn">, drawn boxed like a button
        $boxes = [];
        // Consecutive inline content becomes one anonymous text block
        $inline = [];
        // The cells of a table without rows, which an anonymous row holds, like browsers
        $cells = [];
        $table = $this->styleSheet->mayDisplayAsTable($element);
        // In a flex container, each inline element is a flex item, and so is each text run between them; not in a label
        // wrapping its box, flex by default, that a page displays otherwise, like the inline-block .checkbox of Bulma:
        // its box and its text are lined up in a line of their own
        $flex = $this->styleSheet->mayBlockifyChildren($element) && !('label' === strtolower($element->localName) && $this->styleSheet->mayDisplayOtherwiseThanItems($element) && array_any(array_values(array_filter(iterator_to_array($element->childNodes, false), static fn (\Dom\Node $node): bool => $node instanceof \Dom\Element)), static fn (\Dom\Element $child) => 'input' === strtolower($child->localName) && \in_array(strtolower(trim($child->getAttribute('type') ?? '')), ['checkbox', 'radio'], true)));
        // Flex in some media only, like the <ul> of the menu of TypeScript below 790px: its inline blocks lined up for the
        // others, in a row that is no box where it is flex
        $sometimesFlex = $flex && !$this->styleSheet->alwaysBlockifiesChildren($element);
        // Where each widget comes from in the text of the children, for the spaces between them in that row: the text
        // of the text nodes and the first and last characters of the elements, not all their text, and the offsets
        // each widget starts and ends at, a text run from where it starts to the element ending it
        $text = '';
        $spans = [];
        $runStart = 0;
        $previous = null;
        $lined = !$flex || $sometimesFlex;
        /** @param list<AbstractWidget> $widgets */
        $settle = function (int $end, ?array $previous, array $widgets) use (&$spans, $lined): void {
            if (!$lined || null === $previous) {
                return;
            }
            [$count, $runStart, $childStart, $childEnd] = $previous;
            for ($i = $count; $i < \count($widgets); ++$i) {
                $spans[spl_object_id($widgets[$i])] = $this->styleSheet->isAnonymous($widgets[$i]) ? [$runStart, min($childStart, $end)] : [$childStart, $childEnd];
            }
        };
        foreach (iterator_to_array($element->childNodes, false) as $child) {
            $settle(\strlen($text), $previous, $widgets);
            $childStart = \strlen($text);
            if (!$inline) {
                $runStart = $childStart;
            }
            if ($lined) {
                $text .= $child instanceof \Dom\Text ? $child->data : ($child instanceof \Dom\Element ? self::textEdges($child) : '');
            }
            $previous = [\count($widgets), $runStart, $childStart, \strlen($text)];
            // An image that cannot be drawn, like the screenshots of a gallery without an image loader: an item too
            if ($flex && $child instanceof \Dom\Element && $this->isTextAlternative($child)) {
                $this->addAnonymousText($inline, $widgets);
                $inline = [];
                if (null !== $widget = $this->buildElement($child)) {
                    $widgets[] = $widget;
                    if ($sometimesFlex) {
                        $controls[] = $widget;
                    }
                }
                continue;
            }
            if ($flex && $child instanceof \Dom\Element && $this->isInline($child)) {
                $tagName = strtolower($child->localName);
                $this->addAnonymousText($inline, $widgets);
                $inline = [];
                if ('br' !== $tagName) {
                    // Displayed as contents, like a <span> around items: its children are items, like those of a <div>
                    $widget = ($this->styleSheet->mayDisplayContents($child) ? $this->registry->get('div') ?? $this->inlineText : $this->inlineText)->create($child, $this);
                    $this->applyAttributes($widget, $child, $tagName);
                    $widgets[] = $widget;
                    if ($sometimesFlex) {
                        $controls[] = $widget;
                    }
                }
                continue;
            }
            // An inline element placed out of the flow, like a positioned badge, floated, like a <span class="float-end">,
            // or displayed as a block, like a link of a menu, is a widget of its own
            if ($child instanceof \Dom\Element && $this->isInline($child) && 'br' !== ($tagName = strtolower($child->localName)) && ($this->styleSheet->mayBeOutOfFlow($child) || $this->styleSheet->mayFloat($child) || $this->styleSheet->mayBeBlock($child))) {
                // Out of the flow in every state, or floated before any text: the text around it stays one, the element
                // before it, at the start of its line; floated after some, below that text
                $flows = ($this->styleSheet->staysOutOfFlow($child) || $this->styleSheet->mayFloat($child, false) && '' === trim(implode('', array_map(static fn (\Dom\Node $node) => $node->textContent ?? '', $inline)))) && !$this->styleSheet->mayBeBlock($child);
                if (!$flows) {
                    $this->addAnonymousText($inline, $widgets);
                    $inline = [];
                }
                $widget = $this->inlineText->create($child, $this);
                $this->applyAttributes($widget, $child, $tagName);
                $widgets[] = $widget;
                if ($table && $this->styleSheet->mayDisplayAsTable($child, true)) {
                    $cells[spl_object_id($widget)] = true;
                }
                // A block in some media only, inline in others, like the donate button of python.org: in the line where
                // it is inline, a line of its own where it is a block
                if ($this->styleSheet->mayBeBlock($child) && !$this->styleSheet->mayBeOutOfFlow($child) && !$this->styleSheet->mayFloat($child) && ($this->styleSheet->mayBeInline($child) || $this->styleSheet->mayBeInlineLevel($child) || $this->styleSheet->staysInlineInEveryMedia($child)) && !$this->holdsBlockInFlow($child) && !$this->holdsControl($child)) {
                    $controls[] = $boxes[] = $widget;
                    $this->styleSheet->setLineItem($widget, 'inline');
                }
                continue;
            }
            // An inline block with a border, like the <a class="btn"> of Bootstrap: in the line of the text around it,
            // boxed like a button, its border above and below it, not cut to its sides by the line
            if ($child instanceof \Dom\Element && $this->isInline($child) && ($this->styleSheet->mayBeInlineBox($child) || $this->isLonePaddedInlineBlock($child))) {
                $this->addAnonymousText($inline, $widgets);
                $inline = [];
                $widget = $this->inlineText->create($child, $this);
                $this->applyAttributes($widget, $child, strtolower($child->localName));
                $widgets[] = $widget;
                $controls[] = $boxes[] = $widget;
                continue;
            }
            if ($child instanceof \Dom\Text || $child instanceof \Dom\Element && ($this->isInline($child) || $this->isTextAlternative($child))) {
                $inline[] = $child;
                continue;
            }
            $this->addAnonymousText($inline, $widgets);
            $inline = [];
            if ($child instanceof \Dom\Element && null !== $widget = $this->buildElement($child)) {
                $widgets[] = $widget;
                if ($table && $this->styleSheet->mayDisplayAsTable($child, true)) {
                    $cells[spl_object_id($widget)] = true;
                }
                if ((!$flex || $sometimesFlex) && $this->isInlineControl($child)) {
                    $controls[] = $widget;
                } elseif ((!$flex || $sometimesFlex) && $this->styleSheet->staysInlineLevel($child) && !$this->styleSheet->mayBeOutOfFlow($child)) {
                    // A block displayed inline-block, like <div class="d-inline-block">: in the line, as wide as its content
                    $controls[] = $boxes[] = $widget;
                } elseif ((!$flex || $sometimesFlex) && ($this->styleSheet->mayBeInlineLevel($child) || $this->styleSheet->mayBeInline($child) && !\in_array(strtolower($child->localName), ['table', 'hr', 'details', 'dialog', 'fieldset', 'form'], true) && !$this->holdsBlockInFlow($child) && !$this->holdsControl($child) && !$this->styleSheet->mayFloat($child)) && !$this->styleSheet->mayBeOutOfFlow($child)) {
                    // Inline-block or inline in some media only, like the items of the menu of TypeScript, blocks on a
                    // narrow screen, or the links of the header of Node: in the line, a line of its own where it is a
                    // block; not one holding blocks or fields, like a form displayed inline, nor a float, placed as one
                    $controls[] = $boxes[] = $widget;
                    $this->styleSheet->setLineItem($widget);
                }
            }
        }
        $settle(\strlen($text), $previous, $widgets);
        $previous = [\count($widgets), $runStart, \strlen($text), \strlen($text)];
        $this->addAnonymousText($inline, $widgets);
        $settle(\strlen($text), $previous, $widgets);
        if ($controls) {
            $widgets = $this->lineUpInlineControls($widgets, $controls, $boxes, [$text, $spans], $sometimesFlex);
        }

        // After the lines of controls, which the rows of cells are no part of
        return $cells ? $this->wrapTableCells($widgets, $cells) : $widgets;
    }

    /**
     * The first and the last characters of the text of an element, apart, for the spaces around it; empty without text.
     */
    private static function textEdges(\Dom\Element $element): string
    {
        $edge = static function (\Dom\Node $node, bool $first) use (&$edge): string {
            if ($node instanceof \Dom\Text) {
                return '' === $node->data ? '' : mb_substr($node->data, $first ? 0 : -1, 1);
            }
            if (!$node instanceof \Dom\Element) {
                return '';
            }
            for ($child = $first ? $node->firstChild : $node->lastChild; null !== $child; $child = $first ? $child->nextSibling : $child->previousSibling) {
                if ('' !== $char = $edge($child, $first)) {
                    return $char;
                }
            }

            return '';
        };
        $first = $edge($element, true);

        return '' === $first ? '' : $first."\x00".$edge($element, false);
    }

    /**
     * Puts each run of the cells of a table without rows in an anonymous row, like browsers: a row where the element is
     * a table, no box where it is not, see CssStyleSheet::setTableRow().
     *
     * @param list<AbstractWidget> $widgets
     * @param array<int, true>     $cells   The cells, by object id
     *
     * @return list<AbstractWidget>
     */
    private function wrapTableCells(array $widgets, array $cells): array
    {
        $result = [];
        $row = null;
        foreach ($widgets as $widget) {
            if (!isset($cells[spl_object_id($widget)])) {
                // Text between cells, like the | between links: in their row, like the anonymous cell of browsers
                if (null !== $row && $this->styleSheet->isAnonymous($widget) && $widget instanceof RichTextWidget) {
                    $row->add($widget);
                    continue;
                }
                $row = null;
                $result[] = $widget;
                continue;
            }
            if (null === $row) {
                $row = new ContainerWidget();
                $this->styleSheet->describe($row, 'tui-table-row');
                $this->styleSheet->setAnonymous($row);
                $this->styleSheet->setTableRow($row);
                $result[] = $row;
            }
            $row->add($widget);
        }

        return $result;
    }

    /**
     * Whether an element is a control laid out in the line of the text around it, like a <button>, an <input> or an
     * image drawn, unless CSS displays it as a block or places it out of the flow.
     */
    private function isInlineControl(\Dom\Element $element): bool
    {
        $tagName = strtolower($element->localName);
        // An inline element holding a form control, like a <span> around a field: in the line, the control in it
        if (\in_array($tagName, self::INLINE, true) && $this->holdsControl($element)) {
            return !$this->styleSheet->mayBeBlock($element) && !$this->styleSheet->mayBeOutOfFlow($element);
        }
        // An image drawn, like an avatar before a name, or the shapes of an <svg>; one that cannot be is text already (see isTextAlternative())
        if ('img' === $tagName || 'svg' === $tagName || 'iframe' === $tagName) {
            $factory = $this->registry->get($tagName);

            return $factory instanceof ImageElement && null === $factory->textAlternative($element, $this) && !$this->styleSheet->mayBeBlock($element) && !$this->styleSheet->mayBeOutOfFlow($element);
        }
        if (!\in_array($tagName, ['button', 'input', 'select', 'textarea', 'meter', 'progress', 'label'], true) || 'input' === $tagName && 'hidden' === strtolower(trim($element->getAttribute('type') ?? ''))) {
            return false;
        }

        return !$this->styleSheet->mayBeBlock($element) && !$this->styleSheet->mayBeOutOfFlow($element);
    }

    /**
     * Lays the controls that are in the line of the text around them, like browsers, side by side with that text
     * and with each other: each run of them in an anonymous row that wraps, no element of the document.
     *
     * @param list<AbstractWidget>                       $widgets
     * @param list<AbstractWidget>                       $controls
     * @param list<AbstractWidget>                       $boxes    The inline boxes among the controls, as wide as their content
     * @param array{string, array<int, array{int, int}>} $sources  The text of the children, and where in it each
     *                                                             widget comes from, for the spaces between them
     * @param bool                                       $items    Whether the parent is flex in some media only:
     *                                                             each row no box where it is flex, its widgets the
     *                                                             items
     *
     * @return list<AbstractWidget>
     */
    private function lineUpInlineControls(array $widgets, array $controls, array $boxes, array $sources, bool $items = false): array
    {
        $result = [];
        $run = [];
        /** @param list<AbstractWidget> $run */
        $flush = function (array &$run) use (&$result, $controls, $boxes, $sources, $items): void {
            // A control alone keeps its line, like a field taking the width of a form; a button alone too, as wide as its
            // content, like under a paragraph, and an inline box
            if (!array_any($run, static fn (AbstractWidget $widget) => \in_array($widget, $controls, true)) || 1 === \count($run) && !$run[0] instanceof ButtonWidget && !\in_array($run[0], $boxes, true)) {
                array_push($result, ...$run);
            } else {
                $row = new ContainerWidget();
                foreach ($run as $widget) {
                    $row->add($widget);
                }
                // Side by side, wrapping, each as wide as its content, the text across from the middle of a taller
                // control; a column apart where the text has a space, not between all of them, like two buttons written
                // together or the ! after the <b> of Hello <b>world</b>!, the space dropped where the line wraps
                $this->styleSheet->describe($row, CssStyleSheet::LINE_TAG, ['style' => 'display: flex; flex-wrap: wrap; column-gap: 0; align-items: center']);
                $this->styleSheet->setAnonymous($row);
                if ($items) {
                    $this->styleSheet->setItemRow($row);
                }
                [$text, $spans] = $sources;
                foreach ($run as $index => $widget) {
                    if (0 === $index || null === ($span = $spans[spl_object_id($widget)] ?? null) || null === $before = $spans[spl_object_id($previous = $run[$index - 1])] ?? null) {
                        continue;
                    }
                    // The text between them, and the edges of their own text, not of an inline box, which drops
                    // the spaces at its ends, like an <li> inline-block
                    $between = substr($text, $before[1], max(0, $span[0] - $before[1]));
                    if ($before[1] > $before[0] && !\in_array($previous, $boxes, true) && preg_match('/.\z/su', mb_scrub(substr($text, max($before[0], $before[1] - 4), min(4, $before[1] - $before[0])), 'UTF-8'), $last)) {
                        $between = $last[0].$between;
                    }
                    if ($span[1] > $span[0] && !\in_array($widget, $boxes, true) && preg_match('/^./su', mb_scrub(substr($text, $span[0], 4), 'UTF-8'), $first)) {
                        $between .= $first[0];
                    }
                    // Not a no-break space, which the text keeps and draws itself
                    if (preg_match('/[ \t\n\r\f]/', $between)) {
                        $this->styleSheet->setSpacedItem($widget);
                    }
                }
                // A text that does not fit after a control goes on beside it, wrapping, like in a paragraph
                ContainerState::of($row)->setInlineFlow();
                $result[] = $row;
            }
            $run = [];
        };
        foreach ($widgets as $widget) {
            if (\in_array($widget, $controls, true) || $this->styleSheet->isAnonymous($widget)) {
                $run[] = $widget;
            } else {
                $flush($run);
                $result[] = $widget;
            }
        }
        $flush($run);

        return $result;
    }

    /**
     * @param list<\Dom\Node>      $nodes
     * @param list<AbstractWidget> $widgets
     */
    private function addAnonymousText(array $nodes, array &$widgets): void
    {
        if (!$nodes) {
            return;
        }
        $parent = $nodes[0]->parentElement;
        $block = $nodes[0]->ownerDocument->createElement('tui-text');
        foreach ($nodes as $node) {
            $block->appendChild($node->cloneNode(true));
        }
        // Like browsers, ignore the newline that ends the text of a block, like the one before </pre> of a block of code
        // scrolled sideways, the last of its text, in an element too, like <code>…\n</code>: it ends its last line,
        // opening no other; the copy changed, not the document
        if (null !== $parent && end($nodes) === $parent->lastChild) {
            // A <br> ends the last line too, like the one ending each line of a block of code of Prism: none after it,
            // whitespace after it collapsing
            $br = $block->lastChild instanceof \Dom\Text && '' === trim($block->lastChild->data, " \t\n\r\f") ? $block->lastChild->previousSibling : $block->lastChild;
            if ($br instanceof \Dom\Element && 'br' === strtolower($br->localName)) {
                $br->remove();
            } else {
                for ($last = $block->lastChild; $last instanceof \Dom\Element; $last = $last->lastChild) {
                }
                if ($last instanceof \Dom\Text && str_ends_with($last->data, "\n")) {
                    $last->data = substr($last->data, 0, -1);
                }
            }
        }
        if ('' !== trim($this->getText($block))) {
            $inline = $this->getInlineNodes($block, true);
            // The text around the blocks of a link, like the caption after the image of a card in a link: the link
            $link = $this->getEnclosingLink($parent);
            $widget = null !== $link || RichTextWidget::linksOf($inline) ? new LinkedTextWidget($inline) : new RichTextWidget($inline);
            if (null !== $link) {
                $this->linkBlock($widget, $link);
            } elseif ($widget instanceof LinkedTextWidget) {
                // Its links are elements of the document, which setInnerHtml() keeps the focus on
                $this->view?->setTextNodes($widget, $nodes);
            }
            $widget->setInlineImages($this->takeInlineImages());
            $widget->setBaseUrl($this->baseUrl);
            $this->styleSheet->describe($widget, 'tui-text');
            $this->styleSheet->setAnonymous($widget);
            $widgets[] = $widget;
        }
    }

    private function applyAttributes(AbstractWidget $widget, \Dom\Element $element, string $tagName): void
    {
        if ($widget instanceof RichTextWidget) {
            $widget->setBaseUrl($this->baseUrl);
        }
        $attributes = $this->attributesOf($element);
        // A password never reaches the stylesheet, where attr(value) could show it
        if ('input' === $tagName && 'password' === strtolower($attributes['type'] ?? '')) {
            unset($attributes['value']);
        }
        if (isset($attributes['id']) && '' !== $attributes['id']) {
            $widget->setId($attributes['id']);
        }
        // Classes live on the widget: the stylesheet mirrors them from there; not those the default style sheet styles
        // widgets of, like the .columns of Bulma, which would lay a block out as a row: the element keeps them
        $kept = [];
        // The classes the widget has already, like the p of a paragraph that the default style sheet styles widgets by:
        // not the element's, which selectors like [class] or .p of the page would match
        $own = preg_split('/\s+/', trim($attributes['class'] ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $internalClasses = array_values(array_diff(array_intersect($widget->getStyleClasses(), array_keys(self::defaultClasses())), $own));
        foreach ($own as $class) {
            if (isset(self::defaultClasses()[$class])) {
                $kept[] = $class;
            } else {
                $widget->addStyleClass($class);
            }
        }
        if ($kept) {
            $attributes['class'] = implode(' ', $kept);
        } else {
            unset($attributes['class']);
        }
        // Accessible name: aria-label, aria-labelledby, <label>, title, alt, else its own
        $ownName = $widget->getLabel();
        // Kept for setInnerHtml() to name it again, when a fragment changes what names it from outside
        if (isset($attributes['id']) || isset($attributes['aria-labelledby'])) {
            $this->view?->setOwnName($widget, $ownName);
        }
        $label = $this->accessibleName($element, $widget, $attributes, $ownName);
        // What takes the focus: the widget, or the field of a dropdown
        $focusTarget = $widget instanceof DropdownWidget ? $widget->getField() : $widget;
        if (null !== $label) {
            $widget->setLabel($label);
            $focusTarget->setLabel($label);
        } elseif ($focusTarget instanceof FocusableInterface) {
            $this->report('a11y:'.$tagName.':'.($attributes['id'] ?? spl_object_id($widget)), \sprintf('The focusable <%s>%s has no accessible name: add aria-label, a <label> or a title.', $tagName, isset($attributes['id']) ? ' #'.$attributes['id'] : ''));
        }
        if (isset($attributes['disabled']) || self::isDisabled($element)) {
            $widget->setState('disabled');
            if ($widget instanceof DropdownWidget) {
                $widget->getField()->setState('disabled');
                $widget->getList()->setState('disabled');
            }
        }
        // The controls of a form, for <form> to submit them: the form of its form attribute, else the one it is in
        if (\in_array($tagName, ['input', 'select', 'textarea', 'button'], true)) {
            $form = self::formOwner($element);
            // What a reset gives it back: as built, kept for a form a splice binds it to later, see setInnerHtml()
            if (null !== $form || $element->hasAttribute('form')) {
                $defaults = Element\FormBinding::defaults($widget, $element);
                $this->view?->setFormDefaults($widget, $defaults);
                if (null !== $form) {
                    $this->bindControl($widget, $element, $form, $defaults);
                }
            }
        }
        // An inert subtree takes neither the focus nor the pointer
        if (isset($attributes['inert'])) {
            $widget->setState('inert');
        }
        $view = $this->view;
        if (null !== $view) {
            // popover: hidden until shown; auto unless manual or hint, like HTML
            if (isset($attributes['popover'])) {
                $mode = strtolower(trim($attributes['popover']));
                $view->setPopover($widget, \in_array($mode, ['manual', 'hint'], true) ? $mode : 'auto');
            }
            // interestfor: pointing at it or focusing it shows the popover it names, like a tooltip
            if (isset($attributes['interestfor']) && '' !== trim($attributes['interestfor'])) {
                $view->setInterestTarget($widget, trim($attributes['interestfor']));
            }
            // Invoker buttons: popovertarget (and its action), or commandfor and command
            if ($widget instanceof ButtonWidget && (isset($attributes['popovertarget']) || isset($attributes['commandfor']))) {
                $target = $attributes['commandfor'] ?? $attributes['popovertarget'];
                $command = isset($attributes['commandfor']) ? $attributes['command'] ?? '' : match (strtolower($attributes['popovertargetaction'] ?? 'toggle')) {
                    'show' => 'show-popover',
                    'hide' => 'hide-popover',
                    default => 'toggle-popover',
                };
                $view->setInvoker($widget, $target);
                $widget->on(PressEvent::class, static fn () => $view->invoke($target, $command, $widget));
            }
            if (isset($attributes['autofocus'])) {
                $view->setAutofocus($focusTarget);
            }
            // The first <legend> of a <fieldset> is drawn in its border, like browsers
            if ('fieldset' === $tagName && $widget instanceof ContainerWidget && 'legend' === strtolower($element->firstElementChild->localName ?? '') && null !== $first = $widget->all()[0] ?? null) {
                ContainerState::of($widget)->setLegend($first);
            }
            if ($this->styleSheet->mayBeInitialTarget($element)) {
                $view->addInitialTarget($widget);
            }
            // A click on a label goes to its control
            if ('label' === $tagName && null !== $control = $attributes['for'] ?? $element->querySelector('input, textarea, select')?->getAttribute('id')) {
                $view->setLabelTarget($widget, $control);
            }
            if (isset($attributes['tabindex']) && is_numeric($attributes['tabindex'])) {
                $view->setTabIndex($focusTarget, (int) $attributes['tabindex']);
            }
            if (isset($attributes['accesskey']) && '' !== $attributes['accesskey']) {
                try {
                    $view->setAccessKey($focusTarget, $attributes['accesskey']);
                } catch (InvalidArgumentException $e) {
                    $this->report('accesskey:'.$attributes['accesskey'], $e->getMessage());
                }
            }
        }
        // Mirrored in the order of the page, whatever classes the element keeps or the widget had already
        $this->styleSheet->describe($widget, $tagName, $attributes, $internalClasses, \count($own) > 1 ? $own : []);
        $view?->setElement($widget, $element);
    }

    /**
     * Composes the declarative shadow roots, like <template shadowrootmode="open">: the content of the template in
     * place of the children of its host, each of them in the slot that names it (<slot name>, their slot attribute),
     * or the default one, or left out; a slot with none shows its own. The elements of each tree, its host and those
     * slotted in it are marked, for the rules of each tree to match there only; those within, after.
     *
     * @return array<int, int> How deep each tree is in others, from 1
     */
    private function composeShadowRoots(\Dom\HTMLDocument $document): array
    {
        return ShadowRoots::compose($document);
    }

    /**
     * @param array<int, int> $depths How deep each shadow tree is
     */
    private function loadStyles(\Dom\ParentNode $document, array $depths = []): void
    {
        // The style sheets of the shadow trees first: those of the document win over them
        $elements = iterator_to_array($document->querySelectorAll('style, link[rel~="stylesheet" i]'));
        // Those of <noscript> are for browsers that do not run scripts: left out while they run
        if ($this->scripting) {
            $elements = array_values(array_filter($elements, static fn (\Dom\Element $element) => null === $element->closest('noscript')));
        }
        usort($elements, static fn (\Dom\Element $a, \Dom\Element $b) => (int) $b->hasAttribute(Selector::TREE_ATTRIBUTE) <=> (int) $a->hasAttribute(Selector::TREE_ATTRIBUTE));
        foreach ($elements as $element) {
            // Read once, like a page: again for a splice, a @scope would be scoped to its parent anew, its rules twice
            if (isset($this->read[$element])) {
                continue;
            }
            $this->read[$element] = true;
            $media = $element->getAttribute('media');
            // The tree a style sheet styles: its own, or the document's when there are shadow trees
            $tree = null !== ($attribute = $element->getAttribute(Selector::TREE_ATTRIBUTE)) ? (int) $attribute : ($depths ? 0 : null);
            if ('style' === strtolower($element->localName)) {
                // A @scope without a root is scoped to the element the <style> is in, like a component's
                $scope = null;
                if (str_contains($element->textContent, '@scope') && null !== $parent = $element->parentElement) {
                    if (!\in_array(strtolower($parent->localName), ['head', 'html'], true)) {
                        $parent->setAttribute('data-tui-scope', (string) ++$this->scopes);
                        $scope = '[data-tui-scope="'.$this->scopes.'"]';
                    }
                }
                $this->styleSheet->add($element->textContent, $media, $scope, $tree, null !== $tree ? $depths[$tree] ?? 0 : 0, $element);
                continue;
            }
            $href = (string) $element->getAttribute('href');
            try {
                $this->styleSheet->import($href, $media, $tree, null !== $tree ? $depths[$tree] ?? 0 : 0, $element);
            } catch (TuiInvalidArgumentException|InvalidArgumentException $e) {
                $this->report('link:'.$href, $e->getMessage());
            }
        }
    }

    /**
     * The form of a control, like its form owner: the <form> its form attribute names, else the one it is in.
     */
    private static function formOwner(\Dom\Element $element): ?\Dom\Element
    {
        if ($element->hasAttribute('form')) {
            $form = $element->ownerDocument?->getElementById((string) $element->getAttribute('form'));

            return null !== $form && 'form' === strtolower($form->localName) ? $form : null;
        }

        return $element->closest('form');
    }

    /**
     * The accessible name of an element: aria-label, aria-labelledby, <label>, title, alt, else the one the widget
     * gave itself ($own), like a <button> by its text, a <details> by its <summary>, or text by what it says, its links
     * included; null for none.
     *
     * @param array<string, string> $attributes
     */
    private function accessibleName(\Dom\Element $element, AbstractWidget $widget, array $attributes, ?string $own): ?string
    {
        $label = $attributes['aria-label'] ?? null;
        if (null === $label && isset($attributes['aria-labelledby'])) {
            $label = implode(' ', array_filter(array_map(fn (string $id) => $this->labelText($element->ownerDocument->getElementById($id)), preg_split('/\s+/', trim($attributes['aria-labelledby'])) ?: [])));
        }
        $label ??= isset($attributes['id']) ? (null !== $this->index ? $this->labelOf($element, $attributes['id']) : $this->labels[$attributes['id']] ?? null) : null;
        $label ??= $attributes['title'] ?? $attributes['alt'] ?? null;
        $label ??= $own;
        if (null === $label || '' === trim($label)) {
            $label = match (true) {
                $widget instanceof CollapsibleWidget => $widget->getSummary(),
                $widget instanceof RichTextWidget => $widget->getPlainText(),
                default => $label,
            };
        }

        $label = null !== $label ? trim(StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($label))) : '';

        return '' !== $label ? $label : null;
    }

    /**
     * What a fragment can name outside it, read before and after it replaces the content of an element: the ids in
     * that content, the ids of the controls the labels in that content or around it name, and the ids of the element
     * and of its ancestors (aria-labelledby and aria-describedby name by them).
     *
     * @return array{array<string, true>, array<string, true>, array<string, true>}
     */
    private static function namers(\Dom\Element $element): array
    {
        $ids = [];
        $around = [];
        $labelled = [];
        foreach ($element->querySelectorAll('[id]') as $node) {
            $ids[(string) $node->getAttribute('id')] = true;
        }
        $labels = iterator_to_array($element->querySelectorAll('label'), false);
        for ($ancestor = $element; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if (null !== $id = $ancestor->getAttribute('id')) {
                $around[$id] = true;
            }
            if ('label' === strtolower($ancestor->localName)) {
                $labels[] = $ancestor;
            }
        }
        foreach ($labels as $label) {
            if (null !== $id = $label->getAttribute('for') ?? $label->querySelector('input, textarea, select, progress')?->getAttribute('id')) {
                $labelled[$id] = true;
            }
        }

        return [$ids, $labelled, $around];
    }

    /**
     * The text of the labels of a document, by the id of what they name; of an element built again by buildFragment(),
     * its labels and those around it only, the ids of the controls they wrap given, the others found by labelOf().
     */
    private function collectLabels(\Dom\HTMLDocument|\Dom\Element $root): void
    {
        $document = $root instanceof \Dom\HTMLDocument ? $root : $root->ownerDocument;
        $labels = iterator_to_array($root->querySelectorAll('label'), false);
        for ($ancestor = $root instanceof \Dom\Element ? $root : null; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if ('label' === strtolower($ancestor->localName)) {
                $labels[] = $ancestor;
            }
        }
        foreach ($labels as $label) {
            if (null !== $for = $label->getAttribute('for')) {
                if (null === $document->getElementById($for)) {
                    $this->report('a11y:label:'.$for, \sprintf('The <label for="%s"> does not label any element.', $for));
                }
                if (null === $this->index) {
                    $this->labels[$for] = $this->labelText($label);
                }
                continue;
            }
            // A label wrapping its control
            $control = $label->querySelector('input, textarea, select, progress');
            if (null !== $control) {
                if (null === $control->getAttribute('id')) {
                    $control->setAttribute('id', 'tui-label-'.spl_object_id($label));
                }
                if (null === $this->index) {
                    $this->labels[$control->getAttribute('id')] = $this->labelText($label);
                }
            }
        }
    }

    /**
     * The text of the label of an element, like collectLabels() gives it: the last one in the order of the document of
     * the <label for> of its id and the label wrapping it, it the first control of that label.
     */
    private function labelOf(\Dom\Element $element, string $id): ?string
    {
        $last = null;
        foreach ($this->index?->labelsFor($id) ?? [] as $label) {
            $last = $label;
        }
        // The closest one around it is the last of those around it
        for ($ancestor = $element->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if ('label' === strtolower($ancestor->localName) && null === $ancestor->getAttribute('for')) {
                if ($ancestor->querySelector('input, textarea, select, progress') === $element && (null === $last || $last->compareDocumentPosition($ancestor) & \Dom\Node::DOCUMENT_POSITION_FOLLOWING)) {
                    $last = $ancestor;
                }
                break;
            }
        }

        return null !== $last ? $this->labelText($last) : null;
    }

    private function labelText(?\Dom\Element $element): string
    {
        if (null === $element) {
            return '';
        }
        $clone = $element->cloneNode(true);
        if (!$clone instanceof \Dom\Element) {
            return '';
        }
        foreach ($clone->querySelectorAll('input, textarea, select, progress') as $control) {
            $control->remove();
        }

        return trim(preg_replace('/\s+/', ' ', $clone->textContent));
    }

    /**
     * Moves the nodes deeper than {@see self::MAX_DEPTH} up into their ancestor at that depth, in document order.
     */
    private function limitDepth(\Dom\Element $root, int $rootDepth = 1): void
    {
        $stack = [[$root, $rootDepth]];
        while ([$element, $depth] = array_pop($stack)) {
            if ($depth < self::MAX_DEPTH) {
                for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                    $stack[] = [$child, $depth + 1];
                }
                continue;
            }
            if (null === $element->firstElementChild) {
                continue;
            }
            // Its descendants in document order: each one moved after the one before it takes its children out of it
            $descendants = [];
            $pending = [$element->firstChild];
            while (null !== $node = array_pop($pending)) {
                $descendants[] = $node;
                if (null !== $node->nextSibling) {
                    $pending[] = $node->nextSibling;
                }
                if (null !== $node->firstChild) {
                    $pending[] = $node->firstChild;
                }
            }
            foreach ($descendants as $node) {
                $element->appendChild($node);
            }
            $this->report('depth', \sprintf('Elements nested deeper than %d levels are drawn as siblings.', self::MAX_DEPTH));
        }
    }

    /**
     * Logs a warning about the document once for each key.
     *
     * @internal
     */
    public function report(string $key, string $message): void
    {
        if (!isset($this->reported[$key])) {
            $this->reported[$key] = true;
            $this->logger?->warning($message);
        }
    }
}
