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

use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Event\BeforeToggleEvent;
use Symfony\Bridge\TuiHtml\Event\CloseEvent;
use Symfony\Bridge\TuiHtml\Event\CommandEvent;
use Symfony\Bridge\TuiHtml\Event\FormSubmitEvent;
use Symfony\Bridge\TuiHtml\Event\LinkEvent;
use Symfony\Bridge\TuiHtml\Event\ToggleEvent;
use Symfony\Bridge\TuiHtml\Exception\LogicException;
use Symfony\Bridge\TuiHtml\Internal\ContainerState;
use Symfony\Bridge\TuiHtml\Internal\Runtime;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Render\StackingOrder;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Widget\ActivatableInterface;
use Symfony\Bridge\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\DropdownWidget;
use Symfony\Bridge\TuiHtml\Widget\EditorWidget;
use Symfony\Bridge\TuiHtml\Widget\ImageMapWidget;
use Symfony\Bridge\TuiHtml\Widget\InputWidget;
use Symfony\Bridge\TuiHtml\Widget\LinkedTextWidget;
use Symfony\Bridge\TuiHtml\Widget\LinksInterface;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\SelectListWidget;
use Symfony\Bridge\TuiHtml\Widget\SliderWidget;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\FocusEvent;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\RendererChangeEvent;
use Symfony\Component\Tui\Focus\FocusManager;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\Tui\Widget\WidgetContainerInterface;

/**
 * The widget tree built from HTML, with what it needs once mounted.
 *
 * Behavior is wired in PHP, on the widgets the view finds:
 *
 *     $view->querySelector('#languages')->on(SelectEvent::class, $onSelect);
 *     $view->mount($tui);
 *
 * @experimental
 */
final class HtmlView
{
    private string $documentTitle = '';
    /** Whether mounting it saved the title of the terminal, put back when it is unmounted */
    private bool $titlePushed = false;
    private ?AbstractWidget $root = null;
    private ?Tui $tui = null;
    /** The renderer its styles were added to, which keeps them after the Tui selects another one */
    private ?Runtime $runtime = null;
    /** @var list<HtmlView> The documents nested in this one, like the srcdoc of an <iframe> */
    private array $frames = [];
    /** @var \WeakMap<AbstractWidget, array{list<AbstractWidget>, ?AbstractWidget}> The open modal dialogs: what they made inert, and the focus before */
    private \WeakMap $modals;
    /** @var \WeakMap<AbstractWidget, string> The return values of the dialogs, see getReturnValue() */
    private \WeakMap $returnValues;
    /** @var \WeakMap<AbstractWidget, 'auto'|'manual'|'hint'> The popovers, like elements with a popover attribute */
    private \WeakMap $popovers;
    /** @var list<AbstractWidget> The popovers open, the last one on top */
    private array $openPopovers = [];
    /** @var \WeakMap<AbstractWidget, string> The id each invoker button acts on, like popovertarget or commandfor */
    private \WeakMap $invokers;
    /** @var \WeakMap<AbstractWidget, string> The id each element with interestfor shows when pointed at or focused */
    private \WeakMap $interestTargets;
    /** @var list<AbstractWidget> The popovers shown by interest, see updateInterest() */
    private array $interestShown = [];
    /** @var \WeakMap<AbstractWidget, \WeakReference<AbstractWidget>> The implicit anchor of each popover, its invoker */
    private \WeakMap $anchors;
    /** @var \WeakMap<AbstractWidget, true> The invokers popovers are anchored to, see keptUnder() */
    private \WeakMap $anchoring;
    /** @var list<AbstractWidget> The widget under the pointer and its ancestors */
    private array $hovered = [];
    /** @var \WeakReference<RichTextWidget>|null The text whose link is under the pointer */
    private ?\WeakReference $hoveredLink = null;
    /** @var list<AbstractWidget> The widget a pointer button is pressed on and its ancestors, which :active matches */
    private array $pressed = [];
    /** The element the last fragment navigated to, which :target matches */
    private ?AbstractWidget $target = null;
    /** @var list<array{RichTextWidget, string, ?\Dom\Element}> The texts ::target-text matches, with their pattern and the element around them, see navigate() */
    private array $targetTexts = [];
    private ?AbstractWidget $autofocus = null;
    /** @var list<AbstractWidget> What may have scroll-initial-target, in document order */
    private array $initialTargets = [];
    /** @var \WeakMap<AbstractWidget, int> */
    private \WeakMap $tabIndexes;
    /** @var \WeakMap<AbstractWidget, string> The id of the control of each label */
    private \WeakMap $labelTargets;
    /** @var \WeakMap<AbstractWidget, array{?string}> The name each widget that can be named from elsewhere gave itself, see setInnerHtml() */
    private \WeakMap $ownNames;
    /** @var \WeakMap<AbstractWidget, list<\Closure(): void>> How a form resets each control it may own: as the control was built, also when a splice binds it later, the user having changed it since, see setInnerHtml() */
    private \WeakMap $formDefaults;
    /** @var array<string, AbstractWidget> */
    private array $accessKeys = [];
    /** Where the root is mounted, see mount() */
    private ?WidgetContainerInterface $parent = null;
    /** @var list<\Closure> The listeners added to the Tui, which unmount() removes */
    private array $listeners = [];
    /** @var array<'press', string> The intervals running on the Tui */
    private array $timers = [];
    /** @var \WeakMap<Tui, \WeakMap<CssStyleSheet, int>>|null The views mounted on each Tui, by their style sheet */
    private static ?\WeakMap $mounted = null;
    /** @var \WeakMap<Tui, \WeakReference<self>>|null The view of the whole screen mounted on each Tui, see mount() */
    private static ?\WeakMap $screens = null;
    /** @var \WeakMap<Tui, \WeakMap<self, true>>|null The views mounted in a part of the screen of each Tui, see unmount() */
    private static ?\WeakMap $parts = null;
    /** Whether mount() mounted it, not attach() as a document nested in another */
    private bool $isMounted = false;
    /** Whether the focus was left to the first Tab before the page of the whole screen was mounted, given back after */
    private ?bool $tabStartBefore = null;
    /** Which mount the listeners belong to, see listen() */
    private int $generation = 0;
    /** @var \WeakReference<InputEvent>|null The key being handled when it moves the focus back, like Shift+F6: text with links it comes into goes to its last link; the event is gone once the key is handled */
    private ?\WeakReference $focusingBack = null;
    /** The document the view was built from, kept for setInnerHtml(): the context of a fragment is its element there */
    private ?\Dom\HTMLDocument $document = null;
    private ?HtmlBuilder $builder = null;
    /** What setInnerHtml() looks up across the document, built at its first call */
    private ?DocumentIndex $index = null;
    private ?string $baseUrl = null;
    /** @var array<int, Element\FormBinding> The forms of the document, by the object id of their element, which they hold */
    private array $formBindings = [];
    /** @var \WeakMap<LinkedTextWidget, list<\Dom\Node>> The nodes of the document each text with links not of an element of its own is built from, like the text of a line around a <span>, see setInnerHtml() */
    private \WeakMap $textNodes;
    /** @var array<int, AbstractWidget> The focusables of the Tui as placeFocusables() last left them, in the order of the tree, by object id: holding them keeps their ids */
    private array $treeOrder = [];
    /** @var \WeakReference<FocusManager>|null The focus manager of the Tui of */
    private ?\WeakReference $treeOrderOf = null;
    /** @var \WeakMap<AbstractWidget, \Dom\Element> The element each widget stands for */
    private \WeakMap $elements;
    /** @var \WeakMap<\Dom\Element, \WeakReference<AbstractWidget>> The widget of each element that has one, held weakly: a widget removed from the tree by other means must not stay alive through its element */
    private \WeakMap $widgets;
    /** @var \WeakMap<CssStyleSheet, \stdClass>|null The animations of each style sheet: the interval drawing their frames on each Tui it is mounted on and what schedules them, shared by the views of the style sheet, see animateOn() */
    private static ?\WeakMap $animations = null;

    /**
     * @internal built by {@see HtmlBuilder}
     */
    public function __construct(private readonly CssStyleSheet $styleSheet)
    {
        $this->modals = new \WeakMap();
        $this->returnValues = new \WeakMap();
        $this->popovers = new \WeakMap();
        $this->anchors = new \WeakMap();
        $this->anchoring = new \WeakMap();
        $this->invokers = new \WeakMap();
        $this->interestTargets = new \WeakMap();
        $this->tabIndexes = new \WeakMap();
        $this->labelTargets = new \WeakMap();
        $this->ownNames = new \WeakMap();
        $this->formDefaults = new \WeakMap();
        $this->elements = new \WeakMap();
        $this->widgets = new \WeakMap();
        $this->textNodes = new \WeakMap();
    }

    /**
     * Replaces the content of an element, like Element::innerHTML: $html is parsed with the element as its context,
     * like rows in a <tbody> or options in a <select>, its widgets built into this view with the style sheet of the
     * page and its <style> elements added to it; what the widgets replaced registered goes with them (focus order,
     * labels, popovers, invokers, form controls, access keys). An autofocus element in it is not focused.
     *
     * The widget of a block container keeps its identity, its children replaced; another, like a <select> or a
     * <table>, is built again whole, in its place.
     *
     * @param AbstractWidget|string $container The widget of the element, or the id of the element
     */
    public function setInnerHtml(AbstractWidget|string $container, string $html): void
    {
        if (null === $this->document || null === $this->builder) {
            throw new LogicException('The view was not built from a document.');
        }
        $element = \is_string($container) ? $this->document->getElementById($container) : $this->elements[$container] ?? null;
        if (null === $element || !\is_string($container) && null === ($this->widgets[$element] ?? null)?->get()) {
            throw new InvalidArgumentException(\is_string($container) ? \sprintf('No element has the id "%s".', $container) : 'The widget does not stand for an element of the document of the view.');
        }
        // Nothing changed for an element outside the body, like a <style> of the <head>
        $body = $this->document->body;
        if (null === $body || !$body->contains($element)) {
            throw new InvalidArgumentException('The element is not in the body.');
        }
        // A style sheet the content replaced or added may display elements outside it otherwise, like an <em> as a
        // block: those are built again with what holds them, else the body, like the page, the controls and frames
        // outside kept with their state
        // The elements those the content replaced may build otherwise, matched in the document as it is still
        $affected = ($restyles = null !== $element->querySelector('style, link') || 1 === preg_match('/<(?:style|link)\b/i', $html)) ? $this->builtBy($element, $body) : null;
        // The state of the elements around it that may be built again, which a browser keeps; with style sheets, those
        // of the other elements too: the popovers open, the focus, the :target
        [$around, $restyled] = $restyles ? [$this->stateAround($element, $body), $this->stateOfBody($element)] : [$this->stateAround($element), null];
        // The link with the focus in a text that may be built again, like the line around a <span>: its <a> element stays
        $link = $this->focusedLink($element);
        // What the parents decide from the content of the element, like whether it is in the line of the text around
        // it: up to the first one its content cannot change that of
        $placements = [];
        for ($ancestor = $element; null !== $ancestor && 'body' !== strtolower($ancestor->localName); $ancestor = $ancestor->parentElement) {
            if (null === $placements[] = $this->builder->placement($ancestor)) {
                break;
            }
        }
        $this->index ??= new DocumentIndex($this->document->documentElement ?? throw new LogicException('The document has no root element.'));
        $before = $this->builder->parseFragment($element, $html, $this->index);
        // Built again: the element, or the closest ancestor with a widget whose place in its parent stays, like the
        // block around a <span> that holds a block no more, and is text of its line; the body when the style sheets
        // may build more otherwise than the elements they match
        $target = $restyles && null === ($affected = $this->addStyles($element, $body, $affected)) ? $body : $element;
        for ($i = 0;; ++$i) {
            $isBody = 'body' === strtolower($target->localName);
            if ($isBody || null !== ($this->widgets[$target] ?? null)?->get() && ($placements[$i] ?? null) === $this->builder->placement($target)) {
                break;
            }
            $target = $target->parentElement ?? throw new LogicException('The element is not in the body.');
        }
        // With what holds the elements the style sheets build otherwise
        $widget = ($this->widgets[$target = $affected ? $this->restyled($target, $affected) : $target] ?? null)?->get();
        \assert(null !== $widget);
        // The widgets of the elements outside the content replaced that are built again with it, like the controls
        // beside a <span> holding a block now: kept as they are, their state, their listeners and the focus with them
        $kept = $this->keptUnder($widget, $element, $restyles && null === $affected, $affected ?? [], $scrolled, $anchored, $opened);
        $skipped = [];
        foreach ($kept as [, $keptWidget]) {
            $skipped[spl_object_id($keptWidget)] = true;
        }
        $old = [];
        self::collect($widget, $old, $skipped);
        // Its children only, when they are replaced: those of a block container, whose own state stays
        // A <dialog> too: kept, it stays open, as a modal or not, like its element
        $factory = $this->builder->getRegistry()->get(strtolower($target->localName));
        $keep = 'body' === strtolower($target->localName) || $widget instanceof ContainerWidget && ContainerWidget::class === $widget::class && ($factory instanceof Element\ContainerElement || $factory instanceof Element\DialogElement);
        if ($keep) {
            array_shift($old);
        }
        $bindings = \count($this->formBindings);
        $this->forget($old);
        $autofocus = $this->autofocus;
        $frames = [];
        foreach ($this->frames as $frame) {
            $frames[spl_object_id($frame)] = true;
        }
        $built = $this->builder->buildFragment($this, $target, $element, $before, $kept, $bindings !== \count($this->formBindings));
        // Not focused: like a page loaded already, the autofocus of an element added later is not run again
        $this->autofocus = $autofocus;
        // Those not built again, like a <p> displayed inline now, text of the line around it: gone, like the others
        if ($kept) {
            $gone = [];
            foreach ($kept as [, $keptWidget]) {
                self::collect($keptWidget, $gone);
            }
            $this->forget($gone);
        }
        // The focus kept, the <details> around it opened again before it is known whether it is still in the tree
        $move = function () use ($keep, $built, $widget, $target, &$new, $around, $link, $restyled, $scrolled, $anchored, $opened, $element): void {
            $this->move($keep, $built, $widget, $target, $new);
            if (null !== $link || null !== $restyled || null !== $scrolled || null !== $anchored || null !== $opened || $this->targetTexts) {
                $this->restoreState($link, $restyled, $scrolled, $anchored, $opened, $new, $element);
            }
            $this->restoreAround($around);
            $this->inertAgain();
        };
        /** @var list<AbstractWidget> $new set by move() */
        $new = [];
        null !== $this->tui ? $this->tui->getFocusManager()->keepFocusWhile($move) : $move();
        if (null !== $tui = $this->tui) {
            // Those it added, those it removed gone from the list already
            foreach ($this->frames as $frame) {
                if (!isset($frames[spl_object_id($frame)])) {
                    $frame->attach($tui);
                }
            }
            $this->placeFocusables($widget instanceof ContainerWidget && $keep ? $widget : $new[0] ?? $widget, $new);
            $tui->requestRender();
        }
    }

    /**
     * Puts what setInnerHtml() built in the place of what it replaced.
     *
     * @param AbstractWidget|list<AbstractWidget> $built
     * @param list<AbstractWidget>                $new   The widgets added
     */
    private function move(bool $keep, AbstractWidget|array $built, AbstractWidget $widget, \Dom\Element $target, array &$new): void
    {
        if ($keep) {
            \assert($widget instanceof ContainerWidget);
            if ($built instanceof AbstractWidget) {
                $children = $built instanceof ContainerWidget ? array_values($built->all()) : [];
                $built instanceof ContainerWidget && $built->clear();
                // What the widget built took from the content, the one kept takes: its attributes as the style sheet
                // knows them (like dir="auto"), the legend of a <fieldset>, the control of a <label>, its name
                $this->adopt($widget, $built, $children);
                // The element stands for the widget kept, not for the one built to hold its children
                $this->forget([$built]);
                $this->setElement($widget, $target);
            } else {
                $children = $built;
            }
            $widget->clear();
            foreach ($children as $child) {
                $widget->add($child);
            }
            $new = $children;
        } else {
            \assert($built instanceof AbstractWidget);
            $parent = $widget->getParent();
            if (!$parent instanceof \Symfony\Component\Tui\Widget\ContainerWidget) {
                throw new LogicException('The widget replaced is not in a container.');
            }
            $parent->replace($widget, $built);
            if ($widget === $this->root) {
                $this->root = $built;
            }
            $new = [$built];
        }
    }

    /**
     * The widgets under the one setInnerHtml() builds again whose elements are outside the content replaced, the
     * outermost ones, by the object id of their element: the elements a browser keeps.
     *
     * @param array<int, \Dom\Element|bool>                       $affected The elements the style sheets build otherwise (true) and those around them (false), by their object id, see restyled()
     * @param list<array{\Dom\Element, int, int}>|null            $scrolled Set to the scroll offsets of the scroll containers built again, by element, when some are scrolled
     * @param list<array{\Dom\Element, \Dom\Element}>|null        $anchored Set to the popovers anchored to an invoker built again, by element,
     *                                                                      with the element of the invoker
     * @param list<array{\Dom\Element, ?\Dom\Element, bool}>|null $opened   Set to the popovers open, see openedIn(), when one is built again, for restoreState()
     *
     * @param-out list<array{\Dom\Element, int, int}>|null $scrolled
     * @param-out list<array{\Dom\Element, \Dom\Element}>|null $anchored
     * @param-out list<array{\Dom\Element, ?\Dom\Element, bool}>|null $opened
     *
     * @return array<int, array{\Dom\Element, AbstractWidget}>
     */
    private function keptUnder(AbstractWidget $widget, \Dom\Element $element, bool $controlsOnly = false, array $affected = [], ?array &$scrolled = null, ?array &$anchored = null, ?array &$opened = null): array
    {
        $kept = [];
        $walk = function (AbstractWidget $parent, bool $controlsOnly) use (&$walk, &$kept, &$scrolled, &$anchored, &$opened, $element, $affected): void {
            foreach ($parent instanceof ParentInterface ? $parent->all() : [] as $child) {
                $childElement = $this->elements[$child] ?? null;
                // Not one of the content replaced, out of the document now, nor one around the element; with the body
                // built again for its styles, the controls and frames alone, the others displayed as the styles say;
                // nor one the styles build otherwise or around one, the controls and frames in it but kept
                if (null !== $childElement && $childElement->isConnected && !$childElement->contains($element) && (\in_array(strtolower($childElement->localName), ['input', 'select', 'textarea', 'button', 'iframe'], true) || !$controlsOnly && !isset($affected[spl_object_id($childElement)]))) {
                    $kept[spl_object_id($childElement)] = [$childElement, $child];
                } elseif (isset($this->anchoring[$child]) && !$this->anchoredTo($anchored, $child) || $this->openPopovers && \in_array($child, $this->openPopovers, true) && !$this->openedIn($opened) || $child instanceof ParentInterface && (null === $childElement || !$childElement->isConnected || !$child instanceof ContainerWidget || !isset(ContainerState::$scrolled[$child]) || self::keepScroll($scrolled, $childElement, $child))) {
                    // Built again, a scroll container scrolled kept its offsets above, like an element a browser keeps
                    $walk($child, $controlsOnly || null !== $childElement && true === ($affected[spl_object_id($childElement)] ?? null));
                }
            }
        };
        // The widget built again itself too, scrolled, when it is not kept, like a <form>
        $walk($widget, $controlsOnly || isset($this->anchoring[$widget]) && !$this->anchoredTo($anchored, $widget) || $this->openPopovers && \in_array($widget, $this->openPopovers, true) && !$this->openedIn($opened) || isset(ContainerState::$scrolled[$widget]) && $widget instanceof ContainerWidget && null !== ($own = $this->elements[$widget] ?? null) && !self::keepScroll($scrolled, $own, $widget));

        return $kept;
    }

    /**
     * Keeps the popovers open, once, when keptUnder() goes through one built again, see restoreState(): whether it did, the
     * walk going on in any case.
     *
     * @param list<array{\Dom\Element, ?\Dom\Element, bool}>|null $opened
     *
     * @param-out list<array{\Dom\Element, ?\Dom\Element, bool}> $opened
     */
    private function openedIn(?array &$opened): bool
    {
        if (null === $opened) {
            $opened = [];
            foreach ($this->openPopovers as $open) {
                if (null !== $of = $this->elements[$open] ?? null) {
                    $anchor = ($this->anchors[$open] ?? null)?->get();
                    $opened[] = [$of, null !== $anchor ? $this->elements[$anchor] ?? null : null, \in_array($open, $this->interestShown, true)];
                }
            }
        }

        return [] !== $opened;
    }

    /**
     * Keeps the popovers anchored to an invoker keptUnder() goes through, built again, see restoreState(): whether
     * some are, the walk going on in any case.
     *
     * @param list<array{\Dom\Element, \Dom\Element}>|null $anchored
     *
     * @param-out list<array{\Dom\Element, \Dom\Element}>|null $anchored
     */
    private function anchoredTo(?array &$anchored, AbstractWidget $invoker): bool
    {
        if (null !== $element = $this->elements[$invoker] ?? null) {
            foreach ($this->anchors as $popover => $anchor) {
                if ($anchor->get() === $invoker && null !== $of = $this->elements[$popover] ?? null) {
                    $anchored[] = [$of, $element];
                }
            }
        }

        return null !== $anchored;
    }

    /**
     * Keeps the scroll offsets of a scroll container keptUnder() goes through, built again; true, the walk going on
     * into it (counted, for the analysers to see a condition).
     *
     * @param list<array{\Dom\Element, int, int}>|null $scrolled
     *
     * @param-out list<array{\Dom\Element, int, int}> $scrolled
     */
    private static function keepScroll(?array &$scrolled, \Dom\Element $element, ContainerWidget $container): bool
    {
        $scrolled[] = [$element, $container->getScrollTop(), $container->getScrollLeft()];

        return \count($scrolled) > 0;
    }

    /**
     * Adds the style sheets in the element setInnerHtml() filled, which apply from now on, to its parents too, and
     * returns the elements they and those it replaced (see builtBy()) may build otherwise, outside it.
     *
     * @param array<int, \Dom\Element>|null $affected Those of the style sheets replaced
     *
     * @return array<int, \Dom\Element>|null
     */
    private function addStyles(\Dom\Element $element, \Dom\Element $body, ?array $affected): ?array
    {
        $this->builder?->loadStylesOf($element);
        if (null === $affected || null === $added = $this->builtBy($element, $body)) {
            return null;
        }

        return array_filter($affected + $added, static fn (\Dom\Element $other) => $other->isConnected && ($other === $element || !$element->contains($other)));
    }

    /**
     * Gives back the focus, the state stateOfBody() kept, the scroll offsets keptUnder() found and the ::target-text
     * of the texts built again, see restoreLink(), restoreBody() and retarget().
     *
     * @param array{LinkedTextWidget, \Dom\Element}|null                                               $link
     * @param array{list<array{\Dom\Element, ?\Dom\Element, bool}>, ?\Dom\Element, ?\Dom\Element}|null $restyled
     * @param list<array{\Dom\Element, int, int}>|null                                                 $scrolled
     * @param list<array{\Dom\Element, \Dom\Element}>|null                                             $anchored
     * @param list<array{\Dom\Element, ?\Dom\Element, bool}>|null                                      $opened
     * @param list<AbstractWidget>                                                                     $new
     * @param \Dom\Element                                                                             $element  The element whose content was replaced
     */
    private function restoreState(?array $link, ?array $restyled, ?array $scrolled, ?array $anchored, ?array $opened, array $new, \Dom\Element $element): void
    {
        // The popovers open built again, by this walk or with the body: open again, without the events of opening
        // them, anchored to the widget of their invoker now; those kept open in their place, the last one on top still
        if (null !== $popovers = $opened ?? $restyled[0] ?? null) {
            $open = [];
            foreach ($popovers as [$of, $anchor, $interest]) {
                if (!$of->isConnected || null === $popover = ($this->widgets[$of] ?? null)?->get()) {
                    continue;
                }
                // Its anchor built again, kept or not: the widget of its element now
                if (null !== $anchor && $anchor->isConnected && null !== ($anchor = ($this->widgets[$anchor] ?? null)?->get()) && ($this->anchors[$popover] ?? null)?->get() !== $anchor) {
                    $this->anchor($popover, $anchor);
                }
                if (!\in_array($popover, $this->openPopovers, true) && isset($this->popovers[$popover])) {
                    $popover->setState('popover-open');
                    if ($interest) {
                        $this->interestShown[] = $popover;
                    }
                }
                $open[] = $popover;
            }
            if ($open) {
                $this->openPopovers = [...$open, ...array_filter($this->openPopovers, static fn (AbstractWidget $popover) => !\in_array($popover, $open, true))];
            }
        }
        // The popovers anchored to an invoker built again: to the widget of its element now
        foreach ($anchored ?? [] as [$popover, $invoker]) {
            if (null !== ($popover = ($this->widgets[$popover] ?? null)?->get()) && $invoker->isConnected && null !== $invoker = ($this->widgets[$invoker] ?? null)?->get()) {
                $this->anchor($popover, $invoker);
            }
        }
        if ($this->targetTexts) {
            $this->retarget($element);
        }
        if (null !== $link) {
            $this->restoreLink($link, $new);
        }
        foreach ($scrolled ?? [] as [$container, $top, $left]) {
            if ($container->isConnected && ($widget = ($this->widgets[$container] ?? null)?->get()) instanceof ContainerWidget) {
                $widget->scrollTo($top);
                $widget->scrollToColumn($left);
            }
        }
        if (null !== $restyled) {
            $this->restoreBody($restyled);
        }
    }

    /**
     * Gives ::target-text to the texts built again for those it matched: the first text of the element around the one
     * it matched that matches its pattern, not one of the element replaced, which goes with it. A limit: it is found
     * again by its pattern, not where it was, so another match that the content adds before it in the element takes
     * it, and a match that started in content replaced inside that element moves to another match of it.
     */
    private function retarget(\Dom\Element $replaced): void
    {
        $root = self::top($this->getRoot());
        $texts = [];
        foreach ($this->targetTexts as [$text, $pattern, $element]) {
            if (self::top($text) !== $root) {
                if (null === $element || $element === $replaced || !$element->isConnected || null === $widget = ($this->widgets[$element] ?? null)?->get()) {
                    continue;
                }
                $found = [];
                self::collect($widget, $found);
                $text = array_find($found, static fn (AbstractWidget $candidate): bool => $candidate instanceof RichTextWidget && 1 === preg_match($pattern, $candidate->getSourceText()));
                if (!$text instanceof RichTextWidget) {
                    continue;
                }
            }
            $texts[] = [$text, $pattern, $element];
        }
        if ($texts !== $this->targetTexts) {
            $this->targetTexts = $texts;
            $this->styleSheet->setTargetTexts(array_map(static fn (array $text): array => [$text[0], $text[1]], $texts));
        }
    }

    /**
     * The element of a widget, else of the closest widget around it with one, like the paragraph of its anonymous text.
     */
    private function elementAround(AbstractWidget $widget): ?\Dom\Element
    {
        for ($around = $widget; null !== $around; $around = $around->getParent()) {
            if (null !== $element = $this->elements[$around] ?? null) {
                return $element;
            }
        }

        return null;
    }

    /**
     * The elements of the body the style sheets in an element may build otherwise (see
     * CssStyleSheet::buildSelectorsOf()), by their object id; null when they may change more, or cannot be told.
     *
     * @return array<int, \Dom\Element>|null
     */
    private function builtBy(\Dom\Element $element, \Dom\Element $body): ?array
    {
        $selectors = $this->styleSheet->buildSelectorsOf(iterator_to_array($element->querySelectorAll('style, link'), false));
        if (null === $selectors) {
            return null;
        }
        foreach ($selectors as $selector => $svg) {
            try {
                if ($svg) {
                    // The shapes of an <svg> only: in one, or around one; apart, not to drop one another selector matched
                    foreach (Selector::queryAll($body->ownerDocument, $selector) as $match) {
                        $shapes[spl_object_id($match)] = null !== $match->closest('svg') || null !== $match->querySelector('svg') ? $match : null;
                    }
                } else {
                    foreach (Selector::queryAll($body->ownerDocument, $selector) as $match) {
                        $affected[spl_object_id($match)] = $match;
                    }
                }
            } catch (\Throwable) {
                return null;
            }
        }
        $affected = ($affected ?? []) + array_filter($shapes ?? []);
        // Not the <html> nor the <head>: the <body> built again
        foreach ($affected as $match) {
            if (!$body->contains($match) || $match === $body) {
                return null;
            }
        }

        return $affected;
    }

    /**
     * What setInnerHtml() builds again for the elements the style sheets build otherwise and the element it builds
     * again: what holds them all, up to the closest element whose place in its parent its content cannot change, like
     * the <p> around an <em> displayed as a block now, else the body. The body too, like for any style sheet, when they
     * hold most of it: building again many parts of it costs more than building it again whole.
     *
     * @param array<int, \Dom\Element|bool> $affected The elements, by their object id; then true for them, false for those around
     *                                                them, for keptUnder(); null for the body built again whole
     *
     * @param-out array<int, bool>|null $affected
     */
    private function restyled(\Dom\Element $target, array &$affected): \Dom\Element
    {
        // The parents of the elements alone already most of the body, see below: not those holding them
        $body = $target->ownerDocument?->body;
        $parents = [];
        foreach ($affected as $other) {
            $parents[spl_object_id($parent = $other instanceof \Dom\Element ? $other->parentElement ?? $other : $target)] = $parent;
        }
        if (null !== $body && self::mostOf(array_sum(array_map(static fn (\Dom\Element $parent): int => 1 + $parent->getElementsByTagName('*')->length, $parents)), \count($parents), $body)) {
            $affected = null;

            return $body;
        }
        $stable = fn (\Dom\Element $element): bool => 'body' === strtolower($element->localName) || !isset($affected[spl_object_id($element)]) && null !== ($this->widgets[$element] ?? null)?->get() && null === $this->builder?->placement($element);
        $holders = [spl_object_id($target) => true];
        foreach ($affected as $other) {
            if (!$other instanceof \Dom\Element) {
                continue;
            }
            $holder = $other->parentElement ?? throw new LogicException('The element is not in the body.');
            while (!$stable($holder)) {
                $holder = $holder->parentElement ?? throw new LogicException('The element is not in the body.');
            }
            $holders[spl_object_id($holder)] = $holder;
            while (!$target->contains($holder)) {
                $target = $target->parentElement ?? throw new LogicException('The element is not in the body.');
            }
        }
        // Above them all: one whose place stays too
        while (!isset($holders[spl_object_id($target)]) && !$stable($target)) {
            $target = $target->parentElement ?? throw new LogicException('The element is not in the body.');
        }
        $size = 0;
        foreach ($holders as $holder) {
            $size += $holder instanceof \Dom\Element ? 1 + $holder->getElementsByTagName('*')->length : 0;
        }
        if (null !== $body && self::mostOf($size, \count(array_filter($holders, static fn (\Dom\Element|bool $holder): bool => $holder instanceof \Dom\Element)), $body)) {
            $affected = null;

            return $body;
        }
        $around = [];
        foreach ($affected as $id => $other) {
            $around[$id] = true;
            for ($ancestor = $other instanceof \Dom\Element ? $other->parentElement : null; null !== $ancestor && !isset($around[$key = spl_object_id($ancestor)]); $ancestor = $ancestor->parentElement) {
                $around[$key] = false;
            }
        }
        $affected = $around;

        return $target;
    }

    /**
     * Whether building the body again whole costs less than building some elements of it again, each with what it
     * holds: when the elements of the body outside them, and its form controls, which a body built again labels and
     * binds again, are fewer than three tenths of them, each built again in its place at a cost of its own (measured
     * on pages of paragraphs, list items, table cells, cards and labelled fields holding an <em> displayed as a block
     * now); one inside another is counted twice, which builds the body again sooner, at its cost as before.
     *
     * @param int $size  The elements, with what they hold
     * @param int $count How many elements
     */
    private static function mostOf(int $size, int $count, \Dom\Element $body): bool
    {
        return 10 * ($body->getElementsByTagName('*')->length - $size + $body->querySelectorAll('input, select, textarea, button')->length) < 3 * $count;
    }

    /**
     * What a user changed in the widgets of an element and of its ancestors that setInnerHtml() may build again, a
     * browser keeping it: the options chosen in a <select> around it, whether a <details> is open, the focus.
     *
     * @return list<array{\Dom\Element, AbstractWidget, ?array{list<\Dom\Element>, int, list<bool>}, ?bool, ?AbstractWidget, ?array{bool, ?AbstractWidget, bool, ?string}}>
     */
    private function stateAround(\Dom\Element $element, ?\Dom\Element $body = null): array
    {
        $around = [];
        $focused = $this->tui?->getFocus();
        $elements = [];
        for ($ancestor = $element; null !== $ancestor && 'body' !== strtolower($ancestor->localName); $ancestor = $ancestor->parentElement) {
            $elements[spl_object_id($ancestor)] = $ancestor;
        }
        // The body built again: its <details> and <dialog> elements outside the content replaced too
        foreach (null !== $body ? $body->querySelectorAll('details, dialog') : [] as $other) {
            if (!$element->contains($other)) {
                $elements[spl_object_id($other)] ??= $other;
            }
        }
        foreach ($elements as $ancestor) {
            if (null === $widget = ($this->widgets[$ancestor] ?? null)?->get()) {
                continue;
            }
            $list = $widget instanceof DropdownWidget ? $widget->getList() : $widget;
            $choice = $list instanceof SelectListWidget ? [Element\SelectElement::options($ancestor), $list->getSelectedItemIndex() ?? 0, array_map(static fn (array $item) => $item['checked'] ?? false, $list->getItems())] : null;
            $open = $widget instanceof CollapsibleWidget ? $widget->isExpanded() : null;
            // A <dialog>: open or not, modal with the focus before it, and its return value
            $dialog = 'dialog' === strtolower($ancestor->localName) ? [$widget->hasState('open'), $this->modals[$widget][1] ?? null, isset($this->modals[$widget]), $this->returnValues[$widget] ?? null] : null;
            $focus = null;
            for ($w = $focused; null !== $w && null === $focus; $w = $w->getParent()) {
                $focus = $w === $widget ? $focused : null;
            }
            if (null !== $choice || null !== $open || null !== $focus || null !== $dialog) {
                $around[] = [$ancestor, $widget, $choice, $open, $focus, $dialog];
            }
        }

        return $around;
    }

    /**
     * What a user changed in the widgets of the body outside an element, which setInnerHtml() builds again for the
     * styles of its new content, a browser keeping it: the popovers open (in their order, with their anchor and
     * whether an interest shows them), the element with the focus and the :target one; the scroll offsets are found by
     * keptUnder(), for any container built again.
     *
     * @return array{list<array{\Dom\Element, ?\Dom\Element, bool}>, ?\Dom\Element, ?\Dom\Element}
     */
    private function stateOfBody(\Dom\Element $element): array
    {
        $outside = function (?AbstractWidget $widget) use ($element): ?\Dom\Element {
            $of = null !== $widget ? $this->elements[$widget] ?? null : null;

            return null !== $of && $of->isConnected && !$element->contains($of) ? $of : null;
        };
        $popovers = [];
        foreach ($this->openPopovers as $open) {
            if (null !== $of = $outside($open)) {
                $popovers[] = [$of, $outside(($this->anchors[$open] ?? null)?->get()), \in_array($open, $this->interestShown, true)];
            }
        }

        return [$popovers, $outside($this->tui?->getFocus()), $outside($this->target)];
    }

    /**
     * Gives the widgets setInnerHtml() built again for the elements of stateOfBody() what the user changed, without
     * the events of doing it: the popovers are not opened now, nor the elements focused.
     *
     * @param array{list<array{\Dom\Element, ?\Dom\Element, bool}>, ?\Dom\Element, ?\Dom\Element} $state
     */
    private function restoreBody(array $state): void
    {
        // Its popovers opened again by restoreState()
        [, $focus, $target] = $state;
        $widgetOf = fn (?\Dom\Element $element): ?AbstractWidget => null !== $element && $element->isConnected ? ($this->widgets[$element] ?? null)?->get() : null;
        if (null !== $this->tui && null !== $widget = $widgetOf($focus)) {
            $focused = $this->tui->getFocus();
            if ($focused !== $widget && (null === $focused || self::top($focused) !== self::top($widget))) {
                $this->tui->setFocus($widget);
            }
        }
        if (null === $this->target && null !== $widget = $widgetOf($target)) {
            $widget->setState('target');
            $this->target = $widget;
        }
    }

    /**
     * The text with links that has the focus and the <a> element of its link with the focus, unless that link is in the
     * content setInnerHtml() replaces.
     *
     * @return array{LinkedTextWidget, \Dom\Element}|null
     */
    private function focusedLink(\Dom\Element $element): ?array
    {
        $focused = $this->tui?->getFocus();
        if (!$focused instanceof LinkedTextWidget || null === $current = $focused->getCurrentHref()) {
            return null;
        }
        if (false === $index = array_search($current, $focused->getLinks(), true)) {
            return null;
        }
        $link = $this->linkElements($focused)[$index] ?? null;

        return null !== $link && !$element->contains($link) ? [$focused, $link] : null;
    }

    /**
     * Gives the focus back to a link of a text setInnerHtml() built again, see focusedLink(): on the text built with
     * its <a> element now.
     *
     * @param array{LinkedTextWidget, \Dom\Element} $link
     * @param list<AbstractWidget>                  $new  The widgets added
     */
    private function restoreLink(array $link, array $new): void
    {
        [$old, $a] = $link;
        if (null === $this->tui || null === $this->root || self::top($old) === self::top($this->root) || !$a->isConnected) {
            return;
        }
        $texts = [];
        foreach ($new as $widget) {
            self::collect($widget, $texts);
        }
        foreach ($texts as $text) {
            // The first one, like the first block of a card, the others out of the focus order
            if ($text instanceof LinkedTextWidget && false !== $index = array_search($a, $this->linkElements($text), true)) {
                $this->tui->setFocus($text);
                $text->focusLink($index);

                return;
            }
        }
    }

    /**
     * The <a href> elements of a text with links, in the order of its links: those of its element, of the nodes of
     * the text of no element it is built from, or the link around it, like the blocks of a card.
     *
     * @return list<\Dom\Element>
     */
    private function linkElements(LinkedTextWidget $text): array
    {
        $links = [];
        $element = $this->elements[$text] ?? null;
        foreach ($this->textNodes[$text] ?? (null !== $element ? [$element] : []) as $node) {
            if (!$node instanceof \Dom\Element) {
                continue;
            }
            $candidates = iterator_to_array($node->querySelectorAll('a[href]'), false);
            if ('a' === strtolower($node->localName) && $node->hasAttribute('href')) {
                array_unshift($candidates, $node);
            }
            foreach ($candidates as $candidate) {
                if ('' !== trim((string) $candidate->getAttribute('href'))) {
                    $links[] = $candidate;
                }
            }
        }

        return $links;
    }

    /**
     * @internal for {@see HtmlBuilder}
     *
     * @param list<\Dom\Node> $nodes The nodes it is built from, or the link around it
     */
    public function setTextNodes(LinkedTextWidget $text, array $nodes): void
    {
        $this->textNodes[$text] = $nodes;
    }

    private static function top(AbstractWidget $widget): AbstractWidget
    {
        while (null !== $parent = $widget->getParent()) {
            $widget = $parent;
        }

        return $widget;
    }

    /**
     * Gives the widgets setInnerHtml() built again for the elements of stateAround() what the user changed.
     *
     * @param list<array{\Dom\Element, AbstractWidget, ?array{list<\Dom\Element>, int, list<bool>}, ?bool, ?AbstractWidget, ?array{bool, ?AbstractWidget, bool, ?string}}> $around
     */
    private function restoreAround(array $around): void
    {
        foreach ($around as [$element, $old, $choice, $open, $focus, $dialog]) {
            $widget = ($this->widgets[$element] ?? null)?->get();
            if (null === $widget || $widget === $old) {
                continue;
            }
            $list = $widget instanceof DropdownWidget ? $widget->getList() : $widget;
            if (null !== $choice && $list instanceof SelectListWidget) {
                [$options, $selected, $checked] = $choice;
                $now = Element\SelectElement::options($element);
                $items = $list->getItems();
                $changed = false;
                foreach ($now as $i => $option) {
                    if (false !== $was = array_search($option, $options, true)) {
                        if (($items[$i]['checked'] ?? false) !== $checked[$was]) {
                            $items[$i]['checked'] = $checked[$was];
                            $changed = true;
                        }
                    }
                }
                if ($changed) {
                    $list->setItems($items);
                }
                if (isset($options[$selected]) && false !== $index = array_search($options[$selected], $now, true)) {
                    $list->setSelectedIndex($index);
                }
            }
            // As the user left it, without the events of opening it: it is not opened now
            if (null !== $open && $widget instanceof CollapsibleWidget) {
                $widget->restoreExpanded($open);
            }
            // A <dialog> built again, like one displayed inline whose content holds a block now: open still, modal
            // over the page again, without the events of opening it
            if (null !== $dialog) {
                [$wasOpen, $focusBefore, $wasModal, $returnValue] = $dialog;
                if (null !== $returnValue) {
                    $this->returnValues[$widget] = $returnValue;
                }
                $widget->setState('open', $wasOpen);
                if ($wasModal) {
                    $this->modals[$widget] = [$this->inertAround($widget), $focusBefore];
                    $widget->setState('modal');
                }
            }
            // The focus was in the widget built again, not in one kept, out of the tree now: on the new one
            if (null !== $focus && null !== $this->tui && self::top($this->tui->getFocus() ?? $focus) !== self::top($widget)) {
                $this->tui->setFocus($widget instanceof DropdownWidget ? $widget->getField() : $widget);
            }
        }
    }

    /**
     * Gives the widget kept by setInnerHtml() what the one built from the same element took from its new content.
     *
     * @param list<AbstractWidget> $children The children of the one built, which the one kept takes
     */
    private function adopt(ContainerWidget $kept, AbstractWidget $built, array $children): void
    {
        if (null !== $description = $this->styleSheet->getDescription($built)) {
            [$internalClasses, $classOrder] = $this->styleSheet->getClassesOf($built);
            $this->styleSheet->describe($kept, $description[0], $description[1], $internalClasses, $classOrder);
        }
        $legend = $built instanceof ContainerWidget ? ContainerState::of($built)->getLegend() : null;
        ContainerState::of($kept)->setLegend(null !== $legend && \in_array($legend, $children, true) ? $legend : null);
        if (isset($this->labelTargets[$built])) {
            $this->labelTargets[$kept] = $this->labelTargets[$built];
        } else {
            unset($this->labelTargets[$kept]);
        }
        $kept->setLabel($built->getLabel());
        if (isset($this->ownNames[$built])) {
            $this->ownNames[$kept] = $this->ownNames[$built];
        }
    }

    /**
     * @internal for {@see HtmlBuilder}
     *
     * @param array<int, Element\FormBinding> $formBindings
     */
    public function keepSource(\Dom\HTMLDocument $document, HtmlBuilder $builder, ?string $baseUrl, array $formBindings): void
    {
        $this->document = $document;
        $this->builder = $builder;
        $this->baseUrl = $baseUrl;
        $this->formBindings = $formBindings;
    }

    /**
     * @internal for {@see HtmlBuilder}
     *
     * @return array{?string, array<int, Element\FormBinding>}
     */
    public function getSourceState(): array
    {
        return [$this->baseUrl, $this->formBindings];
    }

    /**
     * @internal for {@see HtmlBuilder}
     */
    public function getDocumentIndex(): ?DocumentIndex
    {
        return $this->index;
    }

    /**
     * @internal for {@see HtmlBuilder}
     */
    public function setElement(AbstractWidget $widget, \Dom\Element $element): void
    {
        $this->elements[$widget] = $element;
        $this->widgets[$element] = \WeakReference::create($widget);
    }

    /**
     * @internal for {@see HtmlBuilder}
     */
    public function getWidget(\Dom\Element $element): ?AbstractWidget
    {
        return ($this->widgets[$element] ?? null)?->get();
    }

    /**
     * @param list<AbstractWidget> $widgets
     * @param array<int, true>     $skipped The widgets left out, with what they hold, by their object id
     */
    private static function collect(AbstractWidget $widget, array &$widgets, array $skipped = []): void
    {
        $widgets[] = $widget;
        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                if (!isset($skipped[spl_object_id($child)])) {
                    self::collect($child, $widgets, $skipped);
                }
            }
        }
    }

    /**
     * Drops what widgets leaving the view registered in it, like an element removed from a document.
     *
     * @param list<AbstractWidget> $widgets
     */
    private function forget(array $widgets): void
    {
        if (!$widgets) {
            return;
        }
        $gone = [];
        $forms = [];
        foreach ($widgets as $widget) {
            $gone[spl_object_id($widget)] = true;
            if (null !== $element = $this->elements[$widget] ?? null) {
                // A form: the bindings are by the object id of its element
                if (($this->formBindings[spl_object_id($element)] ?? null)?->getForm() === $widget) {
                    $forms[] = spl_object_id($element);
                }
                if (($this->widgets[$element] ?? null)?->get() === $widget) {
                    unset($this->widgets[$element]);
                }
                unset($this->elements[$widget]);
            }
            if ($widget instanceof LinkedTextWidget) {
                unset($this->textNodes[$widget]);
            }
            // Not held for the next placement of focusables: removed, its object id may be another's
            if (($this->treeOrder[spl_object_id($widget)] ?? null) === $widget) {
                unset($this->treeOrder[spl_object_id($widget)]);
            }
            unset($this->ownNames[$widget], $this->tabIndexes[$widget], $this->labelTargets[$widget], $this->popovers[$widget], $this->invokers[$widget], $this->interestTargets[$widget], $this->returnValues[$widget]);
            if (null !== $modal = $this->modals[$widget] ?? null) {
                // A modal dialog removed gives the page back
                foreach ($modal[0] as $inerted) {
                    $inerted->setState('inert', false);
                }
                unset($this->modals[$widget]);
            }
            Element\FormBinding::of($widget)?->remove($widget);
        }
        $isGone = static fn (?AbstractWidget $widget): bool => null !== $widget && isset($gone[spl_object_id($widget)]);
        $this->openPopovers = array_values(array_filter($this->openPopovers, static fn (AbstractWidget $w) => !$isGone($w)));
        $this->interestShown = array_values(array_filter($this->interestShown, static fn (AbstractWidget $w) => !$isGone($w)));
        $this->initialTargets = array_values(array_filter($this->initialTargets, static fn (AbstractWidget $w) => !$isGone($w)));
        $this->accessKeys = array_filter($this->accessKeys, static fn (AbstractWidget $w) => !$isGone($w));
        if (array_any($this->hovered, $isGone)) {
            $this->hovered = [];
            $this->hoveredLink = null;
        }
        if (array_any($this->pressed, $isGone)) {
            $this->release();
        }
        if ($isGone($this->autofocus)) {
            $this->autofocus = null;
        }
        if ($isGone($this->target)) {
            $this->target = null;
        }
        // The forms removed: the controls outside them that their form attribute bound to them are bound to none
        foreach ($forms as $key) {
            $this->formBindings[$key]->clear();
            unset($this->formBindings[$key]);
        }
        // The documents nested in what is removed go with it
        foreach ($this->frames as $i => $frame) {
            if (null !== $frame->root && $isGone($frame->root)) {
                if (null !== $this->tui) {
                    $frame->detach($this->tui);
                }
                unset($this->frames[$i]);
            }
        }
        $this->frames = array_values($this->frames);
    }

    /**
     * Puts the focusable widgets added in a subtree where they are in the tree in the focus order, not after all the
     * others, which the order of their addition would leave them at.
     *
     * @param list<AbstractWidget> $added
     */
    private function placeFocusables(AbstractWidget $subtree, array $added): void
    {
        $focusables = [];
        $walk = static function (AbstractWidget $widget) use (&$walk, &$focusables): void {
            if ($widget instanceof FocusableInterface) {
                $focusables[] = $widget;
            }
            if ($widget instanceof ParentInterface) {
                foreach ($widget->all() as $child) {
                    $walk($child);
                }
            }
        };
        foreach ($added as $widget) {
            $walk($widget);
        }
        if (!$focusables || null === $this->tui) {
            return;
        }
        $focusManager = $this->tui->getFocusManager();
        $moving = [];
        foreach ($focusables as $widget) {
            $moving[spl_object_id($widget)] = true;
        }
        $others = array_values(array_filter($focusManager->all(), static fn (AbstractWidget $widget): bool => !isset($moving[spl_object_id($widget)])));
        // The others in the order of the tree: those of the last placement, less those removed since, or else all of
        // them checked; not when the application added some elsewhere, like before the page in the Tui
        $ids = array_map(spl_object_id(...), $others);
        $inTreeOrder = $this->treeOrderOf?->get() === $focusManager && array_keys(array_intersect_key($this->treeOrder, array_flip($ids))) === $ids || self::inTreeOrder($others);
        if ($inTreeOrder) {
            // The first focusable after the subtree, found by halves among them, not by going through what follows the
            // subtree, like the long text after a form
            $path = [];
            for ($current = $subtree; null !== $parent = $current->getParent(); $current = $parent) {
                $path[spl_object_id($parent)] = $current;
            }
            [$low, $high] = [0, \count($others)];
            while ($low < $high) {
                $middle = ($low + $high) >> 1;
                if (self::isAfter($others[$middle], $subtree, $path)) {
                    $high = $middle;
                } else {
                    $low = $middle + 1;
                }
            }
            $next = $others[$low] ?? null;
        } else {
            $next = self::nextInTree($subtree);
        }
        $focusManager->moveBefore($focusables, $next);
        if ($inTreeOrder) {
            $all = $focusManager->all();
            $this->treeOrder = array_combine(array_map(spl_object_id(...), $all), $all);
            $this->treeOrderOf = \WeakReference::create($focusManager);
        } else {
            $this->treeOrder = [];
            $this->treeOrderOf = null;
        }
    }

    /**
     * Whether focusables are in the order of the tree, each after the one before it.
     *
     * @param list<AbstractWidget> $widgets
     */
    private static function inTreeOrder(array $widgets): bool
    {
        for ($i = 1, $count = \count($widgets); $i < $count; ++$i) {
            $path = [];
            for ($current = $widgets[$i - 1]; null !== $parent = $current->getParent(); $current = $parent) {
                $path[spl_object_id($parent)] = $current;
            }
            // One in the one before, like a link in a <details>, is after it
            if (!self::isAfter($widgets[$i], $widgets[$i - 1], $path) && !self::isInside($widgets[$i - 1], $widgets[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The first focusable after a subtree in the tree, gone through.
     */
    private static function nextInTree(AbstractWidget $subtree): ?AbstractWidget
    {
        $find = static function (AbstractWidget $widget) use (&$find): ?AbstractWidget {
            if ($widget instanceof FocusableInterface) {
                return $widget;
            }
            foreach ($widget instanceof ParentInterface ? $widget->all() : [] as $child) {
                if (null !== $found = $find($child)) {
                    return $found;
                }
            }

            return null;
        };
        for ($current = $subtree; null !== $parent = $current->getParent(); $current = $parent) {
            $after = false;
            foreach ($parent instanceof ParentInterface ? $parent->all() : [] as $sibling) {
                if ($after && null !== $next = $find($sibling)) {
                    return $next;
                }
                $after = $after || $sibling === $current;
            }
        }

        return null;
    }

    /**
     * Whether a widget is after a subtree in the order of the tree, not in it: one of another tree is.
     *
     * @param array<int, AbstractWidget> $path The child each ancestor of the subtree holds it in, by the object id of the ancestor
     */
    private static function isAfter(AbstractWidget $widget, AbstractWidget $subtree, array $path): bool
    {
        // Up to the first ancestor the subtree is in too
        for ($child = $widget; null !== $parent = $child->getParent(); $child = $parent) {
            if ($child === $subtree) {
                return false;
            }
            if (null !== $common = $path[spl_object_id($parent)] ?? null) {
                $siblings = $parent instanceof ParentInterface ? $parent->all() : [];

                return array_search($child, $siblings, true) > array_search($common, $siblings, true);
            }
        }

        // The subtree itself, or a widget of another tree
        return $child !== $subtree;
    }

    /**
     * The widget of the <html> element, holding the one of <body>.
     */
    public function getRoot(): AbstractWidget
    {
        return $this->root ?? throw new LogicException('The view has no root widget yet.');
    }

    /**
     * The widget of the <body> element, holding the widgets of its children.
     */
    public function getBody(): ContainerWidget
    {
        $root = $this->getRoot();
        $body = $root instanceof ContainerWidget ? $root->all()[0] ?? null : null;

        return $body instanceof ContainerWidget ? $body : throw new LogicException('The view has no body widget.');
    }

    public function getStyleSheet(): CssStyleSheet
    {
        return $this->styleSheet;
    }

    /**
     * Adds a document nested in this one, like the srcdoc of an <iframe>: its root is in the tree of this one, and
     * it is mounted with it, its own styles and focus order with it.
     *
     * @internal
     */
    public function addFrame(self $frame): void
    {
        $this->frames[] = $frame;
    }

    /**
     * @return list<HtmlView>
     */
    public function getFrames(): array
    {
        return $this->frames;
    }

    public function getById(string $id): AbstractWidget
    {
        return $this->getRoot()->findById($id) ?? throw new InvalidArgumentException(\sprintf('No widget found with id "%s".', $id));
    }

    public function querySelector(string $selector): ?AbstractWidget
    {
        return $this->querySelectorAll($selector)[0] ?? null;
    }

    /**
     * @return list<AbstractWidget>
     */
    public function querySelectorAll(string $selector): array
    {
        return $this->styleSheet->querySelectorAll($selector, $this->getRoot());
    }

    /**
     * Highlights texts of the page, like the CSS Custom Highlight API: drawn in the styles of the ::highlight(name)
     * rules, like ::highlight(search) { background: yellow }, over those of the text; a highlight registered later is
     * drawn over the others.
     *
     * A text is found in each text block, across its inline elements, its spaces matching any whitespace; the case
     * does not matter unless $matchCase. Calling it again with the name replaces the texts.
     *
     * @param string|list<string> $texts
     */
    public function highlight(string $name, string|array $texts, bool $matchCase = false): void
    {
        if (!preg_match('/^-?[a-zA-Z_][\w-]*$/', $name)) {
            throw new InvalidArgumentException(\sprintf('The highlight name "%s" is not a CSS identifier.', $name));
        }
        $this->styleSheet->setHighlight($name, (array) $texts, $matchCase);
        $this->tui?->requestRender();
    }

    /**
     * Removes a highlight, see highlight().
     */
    public function removeHighlight(string $name): void
    {
        $this->styleSheet->setHighlight($name, null);
        $this->tui?->requestRender();
    }

    /**
     * Computes the styles of the view now, without rendering it.
     *
     * Rendering does it on each frame; call this to inspect styles or the
     * formatted text of a view that is not mounted, like in tests.
     *
     * @return $this
     */
    public function computeStyles(?MediaContext $media = null): static
    {
        $this->styleSheet->prepare($this->getRoot(), $media ?? new MediaContext());

        return $this;
    }

    public function getAutofocus(): ?AbstractWidget
    {
        return $this->autofocus;
    }

    /**
     * Adds the view to a Tui: its root, its styles, its focus order and
     * access keys, then focuses the autofocus element.
     *
     * A view of the whole screen replaces the one mounted before it, which is
     * unmounted, like a browser going to another page.
     *
     * @param WidgetContainerInterface|null $parent Where to add the root, the root of the Tui by default
     *
     * @return $this
     */
    public function mount(Tui $tui, ?WidgetContainerInterface $parent = null): static
    {
        Runtime::of($tui);
        // Mounted already, or attached as a document nested in another: from there first, counted once
        if (null !== $this->tui && !$this->isMounted) {
            $this->detach($this->tui);
        }
        $this->unmount();
        // A page of the whole screen replaces the one before it, like a browser going to another page: unmounted, not
        // kept behind it
        if (null !== $parent) {
            self::$parts ??= new \WeakMap();
            $parts = self::$parts[$tui] ?? null;
            if (null === $parts) {
                /** @var \WeakMap<self, true> $parts */
                $parts = new \WeakMap();
                self::$parts[$tui] = $parts;
            }
            $parts[$this] = true;
        } else {
            self::$screens ??= new \WeakMap();
            if (null !== $previous = (self::$screens[$tui] ?? null)?->get()) {
                $previous->unmount();
            }
            self::$screens[$tui] = \WeakReference::create($this);
        }
        $this->tui = $tui;
        $this->isMounted = true;
        // Before the widgets are attached, so that the first one focused is right
        $focusManager = $tui->getFocusManager();
        // A page of the whole screen leaves the focus to the first Tab, like browsers: none of its widgets takes it by
        // itself; a view in a part of the screen leaves the focus of the application as it is
        if (null === $parent) {
            $this->tabStartBefore = $focusManager->isTabStart();
            $focusManager->setTabStart();
        }
        foreach ($this->tabIndexes as $widget => $tabIndex) {
            $focusManager->setTabIndex($widget, $tabIndex);
        }
        $this->provideStyles($tui);
        // The documents nested in this one: their root is in its tree already
        foreach ($this->frames as $frame) {
            $frame->attach($tui);
        }
        null !== $parent ? $parent->add($this->getRoot()) : $tui->add($this->getRoot());
        $this->parent = $parent;
        // The title of the document is the one of the terminal, unless the view is a part of the screen
        // The title of the terminal before saved, put back when it is unmounted
        if (null === $parent && '' !== $this->documentTitle) {
            $this->titlePushed = true;
            $tui->getTerminal()->pushTitle();
            $tui->getTerminal()->setTitle($this->documentTitle);
        }
        $this->listen($tui);
        // Like browsers, nothing has the focus until the first Tab or an autofocus element, no focus ring shown on a page
        // nobody went through yet: the first Tab goes to the first element of the tab order
        if ($this->autofocus instanceof FocusableInterface) {
            $tui->setFocus($this->autofocus);
        } elseif (null !== ($focused = $tui->getFocus()) && $this->owns($focused)) {
            $tui->setFocus(null);
        }
        // scroll-initial-target: nearest, where its scroll containers start, the first one in the document
        if (null !== $target = $this->initialTargets[0] ?? null) {
            Runtime::of($tui)->reveal($target);
        }

        return $this;
    }

    /**
     * Removes the view from the Tui it is mounted on, like a page left: its root, its styles unless another view mounted
     * there shares them, its focus order and its listeners. Nothing of the Tui holds the view after, which may be
     * mounted again.
     *
     * @return $this
     */
    public function unmount(): static
    {
        // Not a document nested in another, which goes with it
        if (null === ($tui = $this->tui) || !$this->isMounted) {
            return $this;
        }
        $this->isMounted = false;
        if (null !== $parts = self::$parts[$tui] ?? null) {
            unset($parts[$this]);
        }
        // The page of the whole screen: the views mounted in parts of it too, which go with it
        if ($this === (self::$screens[$tui] ?? null)?->get() && null !== $root = $this->root) {
            $inside = [];
            foreach (self::$parts[$tui] ?? [] as $part => $_) {
                for ($widget = $part->parent instanceof AbstractWidget ? $part->parent : null; null !== $widget; $widget = $widget->getParent()) {
                    if ($widget === $root) {
                        $inside[] = $part;
                        break;
                    }
                }
            }
            foreach ($inside as $part) {
                $part->unmount();
            }
        }
        if (null !== ($focused = $tui->getFocus()) && $this->owns($focused)) {
            $tui->setFocus(null);
        }
        $this->detach($tui);
        self::releaseDocumentsOf($this->styleSheet);
        // The page of the whole screen no longer: the focus left to the first Tab no more, and the title of the terminal
        // before it back
        if ($this === (self::$screens[$tui] ?? null)?->get()) {
            unset(self::$screens[$tui]);
            $tui->getFocusManager()->setTabStart($this->tabStartBefore ?? false);
            $this->tabStartBefore = null;
            // Cleared where the terminal keeps no stack of titles
            if ($this->titlePushed) {
                $this->titlePushed = false;
                $tui->getTerminal()->setTitle('');
                $tui->getTerminal()->popTitle();
            }
        }
        if (null !== $root = $this->root) {
            null !== $this->parent ? $this->parent->remove($root) : $tui->remove($root);
        }
        $this->parent = null;

        return $this;
    }

    /**
     * The views mounted on a Tui: the page of the whole screen first, if any, then those mounted in parts of it, like
     * to give them what the pointer does.
     *
     * @return list<self>
     */
    public static function getMountedViews(Tui $tui): array
    {
        $views = [];
        if (null !== $screen = (self::$screens[$tui] ?? null)?->get()) {
            $views[] = $screen;
        }
        foreach (self::$parts[$tui] ?? [] as $part => $_) {
            $views[] = $part;
        }

        return $views;
    }

    /**
     * Lets a style sheet drop what it keeps of the documents it read, unless a view mounted somewhere uses it still.
     *
     * @internal for {@see HtmlBuilder}
     */
    public static function releaseDocumentsOf(CssStyleSheet $styleSheet): void
    {
        foreach (self::$mounted ?? [] as $sheets) {
            if (isset($sheets[$styleSheet])) {
                return;
            }
        }
        $styleSheet->releaseDocuments();
    }

    /**
     * Opens a dialog, not as a modal, like HTMLDialogElement::show(): in the flow of the page, which stays interactive;
     * the focus goes to its first focusable widget.
     */
    public function show(AbstractWidget $dialog): void
    {
        if ($dialog->hasState('open') || !$this->beforeOpening($dialog)) {
            return;
        }
        $dialog->setState('open');
        $this->focusFirstIn($dialog);
        $this->tui?->requestRender();
        $dialog->dispatch(new ToggleEvent($dialog, 'closed', 'open'));
    }

    /**
     * Opens a dialog as a modal, like HTMLDialogElement::showModal(): centered over the page, which is inert until it is
     * closed; the focus goes to its first focusable widget, and Escape closes it.
     */
    public function showModal(AbstractWidget $dialog): void
    {
        // One open in the page becomes a modal, already open: no toggle events
        $wasOpen = $dialog->hasState('open');
        if (isset($this->modals[$dialog]) || !$wasOpen && !$this->beforeOpening($dialog)) {
            return;
        }
        $this->modals[$dialog] = [$this->inertAround($dialog), $this->tui?->getFocus()];
        $dialog->setState('open')->setState('modal');
        $this->focusFirstIn($dialog);
        $this->tui?->requestRender();
        if (!$wasOpen) {
            $dialog->dispatch(new ToggleEvent($dialog, 'closed', 'open'));
        }
    }

    /**
     * Makes inert again the page around the modal dialogs open, once setInnerHtml() built some of it again, like a
     * <table> beside one: what it built is inert, what it replaced given back.
     */
    private function inertAgain(): void
    {
        $modals = [];
        foreach ($this->modals as $dialog => $modal) {
            $modals[] = [$dialog, ...$modal];
        }
        foreach ($modals as [$dialog, $inerted, $focus]) {
            foreach ($inerted as $widget) {
                $widget->setState('inert', false);
            }
            $this->modals[$dialog] = [$this->inertAround($dialog), $focus];
        }
    }

    /**
     * Makes the page around a modal dialog inert: the siblings of it and of its ancestors.
     *
     * @return list<AbstractWidget> Those it made inert
     */
    private function inertAround(AbstractWidget $dialog): array
    {
        $inerted = [];
        for ($current = $dialog; null !== $current && $current !== $this->getRoot(); $current = $parent) {
            $parent = $current->getParent();
            foreach ($parent instanceof ParentInterface ? $parent->all() : [] as $sibling) {
                if ($sibling !== $current && !$sibling->hasState('inert')) {
                    $sibling->setState('inert');
                    $inerted[] = $sibling;
                }
            }
        }

        return $inerted;
    }

    /**
     * Makes a widget a popover, like the popover attribute: hidden until shown, then over the page; an auto one closes
     * on Escape, on a click outside it, and when another auto one opens, a manual one only when asked.
     *
     * @param 'auto'|'manual'|'hint' $mode
     *
     * @internal built by {@see HtmlBuilder}
     */
    public function setPopover(AbstractWidget $widget, string $mode): void
    {
        $this->popovers[$widget] = $mode;
    }

    /**
     * Shows the popover of an id while an element is pointed at or focused, like interestfor.
     *
     * @internal built by {@see HtmlBuilder}
     */
    public function setInterestTarget(AbstractWidget $widget, string $targetId): void
    {
        $this->interestTargets[$widget] = $targetId;
    }

    /**
     * Makes a button act on the element of an id, like popovertarget or commandfor, a click outside a popover it
     * opens not closing it first.
     *
     * @internal built by {@see HtmlBuilder}
     */
    public function setInvoker(AbstractWidget $button, string $targetId): void
    {
        $this->invokers[$button] = $targetId;
    }

    /**
     * Shows a popover, like HTMLElement::showPopover(): the auto ones open that are not around it close.
     */
    public function showPopover(AbstractWidget $popover): void
    {
        if (!isset($this->popovers[$popover]) || \in_array($popover, $this->openPopovers, true)) {
            return;
        }
        $popover->dispatch($before = new BeforeToggleEvent($popover, 'closed', 'open'));
        if ($before->isPropagationStopped() || \in_array($popover, $this->openPopovers, true)) {
            return;
        }
        // An auto popover closes the other auto and hint ones; a hint one, the other hints only
        if (\in_array($this->popovers[$popover], ['auto', 'hint'], true)) {
            $closing = 'auto' === $this->popovers[$popover] ? ['auto', 'hint'] : ['hint'];
            foreach ($this->openPopovers as $open) {
                if (\in_array($this->popovers[$open] ?? null, $closing, true) && !self::isInside($open, $popover)) {
                    $this->hidePopover($open);
                }
            }
        }
        $popover->setState('popover-open');
        $this->openPopovers[] = $popover;
        $this->tui?->requestRender();
        $popover->dispatch(new ToggleEvent($popover, 'closed', 'open'));
    }

    /**
     * Hides a popover, like HTMLElement::hidePopover(), and those open inside it.
     */
    public function hidePopover(AbstractWidget $popover): void
    {
        if (!\in_array($popover, $this->openPopovers, true)) {
            return;
        }
        $popover->dispatch(new BeforeToggleEvent($popover, 'open', 'closed'));
        if (!\in_array($popover, $this->openPopovers, true)) {
            return;
        }
        foreach ($this->openPopovers as $open) {
            if ($open !== $popover && self::isInside($popover, $open)) {
                $this->hidePopover($open);
            }
        }
        $popover->setState('popover-open', false);
        $this->openPopovers = array_values(array_filter($this->openPopovers, static fn (AbstractWidget $open) => $open !== $popover));
        $this->tui?->requestRender();
        $popover->dispatch(new ToggleEvent($popover, 'open', 'closed'));
    }

    public function togglePopover(AbstractWidget $popover): void
    {
        \in_array($popover, $this->openPopovers, true) ? $this->hidePopover($popover) : $this->showPopover($popover);
    }

    /**
     * Runs a command on the element of an id, like an invoker button: show-modal, close, request-close,
     * show-popover, hide-popover or toggle-popover, after dispatching a CommandEvent on it, which can stop
     * them; a custom command, starting with two dashes (--next), only dispatches it.
     */
    public function invoke(string $targetId, string $command, ?AbstractWidget $invoker = null): void
    {
        if (null === $target = $this->getRoot()->findById($targetId)) {
            return;
        }
        $custom = str_starts_with($command, '--');
        if (!$custom && !\in_array($command = strtolower($command), ['show-modal', 'close', 'request-close', 'show-popover', 'hide-popover', 'toggle-popover'], true)) {
            return;
        }
        $target->dispatch($event = new CommandEvent($target, $command, $invoker));
        if ($custom || $event->isPropagationStopped()) {
            return;
        }
        // The invoker of a popover is its implicit anchor, like in HTML
        if (null !== $invoker && str_ends_with($command, '-popover')) {
            $this->anchor($target, $invoker);
        }
        match ($command) {
            'show-modal' => $this->showModal($target),
            'close' => $this->close($target),
            'request-close' => $this->requestClose($target),
            'show-popover' => $this->showPopover($target),
            'hide-popover' => $this->hidePopover($target),
            'toggle-popover' => $this->togglePopover($target),
            default => null,
        };
    }

    /**
     * Anchors a popover to its invoker, like in HTML, see restoreBody().
     */
    private function anchor(AbstractWidget $popover, AbstractWidget $invoker): void
    {
        $this->anchors[$popover] = \WeakReference::create($invoker);
        $this->anchoring[$invoker] = true;
        Runtime::find($this->tui)?->setImplicitAnchor($popover, $invoker);
    }

    /**
     * Closes a dialog, like HTMLDialogElement::close(): a modal one gives the page and the focus back; a CloseEvent is
     * dispatched on it, with its return value, which $returnValue sets.
     */
    public function close(AbstractWidget $dialog, ?string $returnValue = null): void
    {
        if (!$dialog->hasState('open')) {
            return;
        }
        $dialog->dispatch(new BeforeToggleEvent($dialog, 'open', 'closed'));
        if (!$dialog->hasState('open')) {
            return;
        }
        if (null !== $returnValue) {
            $this->returnValues[$dialog] = $returnValue;
        }
        $dialog->setState('open', false)->setState('modal', false);
        if (null !== $modal = $this->modals[$dialog] ?? null) {
            unset($this->modals[$dialog]);
            [$inerted, $focus] = $modal;
            foreach ($inerted as $widget) {
                $widget->setState('inert', false);
            }
            if ($focus instanceof FocusableInterface) {
                $this->tui?->setFocus($focus);
            }
        }
        $this->tui?->requestRender();
        $dialog->dispatch(new ToggleEvent($dialog, 'open', 'closed'));
        $dialog->dispatch(new CloseEvent($dialog, $this->getReturnValue($dialog)));
    }

    /**
     * Asks a dialog to close, like HTMLDialogElement::requestClose(), Escape and a click outside one of closedby="any":
     * a CancelEvent is dispatched on it first, and stopping it keeps the dialog open.
     */
    public function requestClose(AbstractWidget $dialog, ?string $returnValue = null): void
    {
        if (!$dialog->hasState('open')) {
            return;
        }
        $dialog->dispatch($event = new CancelEvent($dialog));
        if (!$event->isPropagationStopped()) {
            $this->close($dialog, $returnValue);
        }
    }

    /**
     * The return value of a dialog, like HTMLDialogElement::returnValue: '' until close() is given one, or a form
     * of method dialog in it is submitted by a button.
     */
    public function getReturnValue(AbstractWidget $dialog): string
    {
        $value = $this->returnValues[$dialog] ?? null;

        return null !== $value ? $value : '';
    }

    /**
     * Sets the return value of a dialog, like assigning HTMLDialogElement::returnValue.
     */
    public function setReturnValue(AbstractWidget $dialog, string $returnValue): void
    {
        $this->returnValues[$dialog] = $returnValue;
    }

    /**
     * Returns the deepest widget drawn at a cell, in terminal coordinates.
     *
     * It reads where the last frame drew the widgets of the mounted view.
     */
    public function widgetAt(int $row, int $col): ?AbstractWidget
    {
        if (null === $this->tui) {
            return null;
        }
        if (($root = $this->getRoot()) instanceof \Symfony\Bridge\TuiHtml\Widget\HtmlRootWidget && null !== $rect = $this->tui->getWidgetRect($root)) {
            $widget = $root->getPresentation()->getWidgetAt($row - $rect->row, $col - $rect->col);

            return null === $widget ? null : $this->pointerTarget($widget);
        }
        // Absolute and fixed elements are over the others, the highest z-index first
        $widget = $this->overlayAt($this->getRoot(), $row, $col);
        $flowHit = false;
        if (null === $widget && null !== $rect = $this->tui->getWidgetRect($this->getRoot())) {
            foreach ($rect->fragments as [$top, $left, $columns, $rows]) {
                if ($row >= $rect->row + $top && $row < $rect->row + $top + $rows && $col >= $rect->col + $left && $col < $rect->col + $left + $columns) {
                    $widget = $this->flowAt($this->getRoot(), $row, $col);
                    $flowHit = null !== $widget;
                    break;
                }
            }
        }
        if (null === $widget) {
            if (!$this->contains($this->getRoot(), $row, $col)) {
                return null;
            }
            $widget = $this->getRoot();
        }
        while (!$flowHit && $widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                if ($this->contains($child, $row, $col)) {
                    $widget = $child;
                    continue 2;
                }
            }
            break;
        }

        return $this->pointerTarget($widget);
    }

    /**
     * Scrolls the scroll container at a cell by some rows (up when negative) and columns (left when negative), like a
     * mouse wheel, horizontal or with Shift: the closest one that can still scroll that way, else the ones around it,
     * up to one with CSS overscroll-behavior: contain.
     *
     * @return bool Whether something scrolled
     */
    public function scrollAt(int $row, int $col, int $rows, int $columns = 0): bool
    {
        $scrolled = false;
        for ($widget = $this->widgetAt($row, $col); null !== $widget && (0 !== $rows || 0 !== $columns); $widget = $widget->getParent()) {
            if ($widget instanceof ContainerWidget) {
                if (0 !== $rows && ContainerState::of($widget)->getClientHeight() > 0) {
                    $before = ContainerState::of($widget)->getScrollTop();
                    if ($before !== ContainerState::of(ContainerState::of($widget)->scrollBy($rows))->getScrollTop()) {
                        $scrolled = true;
                        $rows = 0;
                    } elseif (ContainerState::of($widget)->isOverscrollContained()) {
                        // overscroll-behavior: contain, the containers around it do not scroll
                        $rows = 0;
                    }
                }
                if (0 !== $columns && ContainerState::of($widget)->getClientWidth() > 0) {
                    $before = ContainerState::of($widget)->getScrollLeft();
                    if ($before !== ContainerState::of(ContainerState::of($widget)->scrollByColumns($columns))->getScrollLeft()) {
                        $scrolled = true;
                        $columns = 0;
                    } elseif (ContainerState::of($widget)->isOverscrollContainedSideways()) {
                        $columns = 0;
                    }
                }
            }
            if ($widget === $this->getRoot()) {
                break;
            }
        }
        if ($scrolled) {
            $this->tui?->requestRender();
        }

        return $scrolled;
    }

    private function pointerTarget(AbstractWidget $widget): ?AbstractWidget
    {
        // An inert subtree takes no pointer: what is behind it does
        // interactivity: inert, inherited: up to the closest ancestor that is not
        while (true === StyleBridge::resolve($widget->getContext(), $widget)?->getInert() && $widget !== $this->getRoot() && null !== $parent = $widget->getParent()) {
            $widget = $parent;
        }
        for ($current = $widget; null !== $current && $current !== $this->getRoot(); $current = $current->getParent()) {
            if ($current->hasState('inert')) {
                $widget = $current->getParent() ?? $widget;
            }
        }
        // pointer-events: none lets the pointer through to what is behind: the closest ancestor that takes it
        while ('none' === strtolower($this->styleSheet->getComputedValue($widget, 'pointer-events') ?? '') && $widget !== $this->getRoot() && null !== $parent = $widget->getParent()) {
            $widget = $parent;
        }

        return 'none' === strtolower($this->styleSheet->getComputedValue($widget, 'pointer-events') ?? '') ? null : $widget;
    }

    /**
     * Returns the target of the link drawn at a cell, of a text or an area of an image map, like the href a browser
     * shows for a link under the pointer; null when there is none.
     */
    public function hrefAt(int $row, int $col): ?string
    {
        $widget = $this->widgetAt($row, $col);
        if ($widget instanceof RichTextWidget && null !== $index = $this->linkAt($widget, $row, $col)) {
            $href = $widget->getLinks()[$index] ?? '';
        } elseif ($widget instanceof ImageMapWidget && null !== $index = $this->areaAt($widget, $row, $col)) {
            $href = $widget->getHrefs()[$index] ?? '';
        } else {
            return null;
        }

        return '' !== $href ? $href : null;
    }

    /**
     * Returns the link drawn at a cell that a click follows: the text with links or the image map, the index of the
     * link or the area, and its target, for followLink(); null when there is none, or it is disabled.
     *
     * @return array{LinkedTextWidget|ImageMapWidget, int, string}|null
     */
    public function getLinkAt(int $row, int $col): ?array
    {
        $widget = $this->widgetAt($row, $col);
        if ($widget instanceof LinkedTextWidget && null !== $index = $this->linkAt($widget, $row, $col)) {
            $href = $widget->getLinks()[$index] ?? '';
        } elseif ($widget instanceof ImageMapWidget && null !== $index = $this->areaAt($widget, $row, $col)) {
            $href = $widget->getHrefs()[$index] ?? '';
        } else {
            return null;
        }

        return '' !== $href && !$widget->hasState('disabled') ? [$widget, $index, $href] : null;
    }

    /**
     * Follows a link of getLinkAt(), wherever it is drawn by then, like a click on it: a link to a fragment of the page
     * goes there, another one takes the focus and dispatches a LinkEvent.
     */
    public function followLink(LinkedTextWidget|ImageMapWidget $widget, int $index): bool
    {
        $href = ($widget instanceof ImageMapWidget ? $widget->getHrefs() : $widget->getLinks())[$index] ?? '';
        // Gone from the view since, or disabled: nothing to follow
        if ('' === $href || $widget->hasState('disabled') || !$this->isMounted || !$this->owns($widget)) {
            return false;
        }
        // Like a click on it: the popovers and dialogs it is outside of close
        $this->release();
        $this->lightDismiss($widget);
        if (str_starts_with($href, '#')) {
            return $this->navigate($href);
        }
        $this->tui?->setFocus($widget);
        if ($widget instanceof ImageMapWidget) {
            return $widget->followArea($index);
        }
        $widget->focusLink($index);

        return $widget->activate();
    }

    /**
     * Returns the CSS cursor of the element at a cell, like "pointer" or
     * "text", null when nothing is there.
     *
     * The keywords are the names of the pointer shapes terminals take with
     * OSC 22 (kitty, foot, WezTerm, xterm...): \e]22;<name>\e\\.
     */
    public function cursorAt(int $row, int $col): ?string
    {
        if (null === $widget = $this->widgetAt($row, $col)) {
            return null;
        }
        // Over a link of a text or an area of an image map, a pointer, like the a[href] { cursor: pointer } of browsers
        if (null !== $this->hrefAt($row, $col)) {
            return 'pointer';
        }
        // The fallback keyword of a list, after url() images
        $cursor = strtolower(trim((string) preg_replace('/^.*,/s', '', $this->styleSheet->getComputedValue($widget, 'cursor') ?? 'auto')));
        if ('auto' === $cursor) {
            // Like browsers: a caret over text you can type in
            return $widget instanceof InputWidget || $widget instanceof EditorWidget ? 'text' : 'default';
        }

        // Only a known keyword: the value goes into a terminal sequence, and the cascade keeps invalid declarations
        return \in_array($cursor, CssStyleSheet::CURSORS, true) ? $cursor : 'default';
    }

    /**
     * Moves the pointer: the widget under it and its ancestors get the
     * "hover" state, so that :hover rules apply like in a browser.
     */
    public function hover(int $row, int $col): ?AbstractWidget
    {
        $widget = $this->widgetAt($row, $col);
        $hovered = [];
        for ($current = $widget; null !== $current; $current = $current === $this->root ? null : $current->getParent()) {
            $hovered[] = $current;
        }
        foreach ($this->hovered as $previous) {
            if (!\in_array($previous, $hovered, true)) {
                $previous->setState('hover', false);
            }
        }
        foreach ($hovered as $current) {
            $current->setState('hover');
        }
        // The link of a text under the pointer matches :hover too, like a:hover { text-decoration: underline }
        $link = $widget instanceof RichTextWidget ? $this->linkAt($widget, $row, $col) : null;
        $hoveredText = $this->hoveredLink?->get();
        $previousLink = $hoveredText?->getHoveredLink();
        if (null !== $hoveredText && $hoveredText !== $widget) {
            $hoveredText->setHoveredLink(null);
        }
        if ($widget instanceof RichTextWidget) {
            $widget->setHoveredLink($link);
        }
        $this->hoveredLink = $widget instanceof RichTextWidget ? \WeakReference::create($widget) : null;
        if ($hovered !== $this->hovered || ($widget !== $hoveredText ? null !== $previousLink || null !== $link : $link !== $previousLink)) {
            // :hover rules repaint on the next frame
            $this->tui?->requestRender();
        }
        $this->hovered = $hovered;
        $this->updateInterest();

        return $widget;
    }

    /**
     * Presses a pointer button on a cell: the widget there and its ancestors
     * get the "active" state until release() or click(), so that :active
     * rules apply like in a browser.
     */
    public function press(int $row, int $col): ?AbstractWidget
    {
        $this->release();
        $widget = $this->widgetAt($row, $col);
        for ($current = $widget; null !== $current; $current = $current === $this->root ? null : $current->getParent()) {
            $this->pressed[] = $current->setState('active');
        }
        if ($this->pressed) {
            $this->tui?->requestRender();
        }
        // A press on a slider takes the focus, and on its track moves the thumb there, like in a browser
        if ($widget instanceof SliderWidget && !$widget->hasState('disabled')) {
            $this->tui?->setFocus($widget);
            $this->slide($widget, $col, false);
        }

        return $widget;
    }

    /**
     * Moves the pointer with the button pressed with press(): a slider pressed follows it, its thumb at the column the
     * pointer is at, wherever the row, like in a browser.
     *
     * @return bool Whether something followed the pointer
     */
    public function drag(int $row, int $col): bool
    {
        $slider = $this->pressed[0] ?? null;

        return $slider instanceof SliderWidget && !$slider->hasState('disabled') && $this->slide($slider, $col, true);
    }

    /**
     * Moves the thumb of a slider to a column of its track, in its content box, inside its border and padding.
     */
    private function slide(SliderWidget $slider, int $col, bool $clamp): bool
    {
        if (null === $rect = $this->tui?->getWidgetRect($slider)) {
            return false;
        }
        $style = StyleBridge::resolve($slider->getContext(), $slider);
        $left = ($style?->getBorder()->left ?? 0) + ($style?->getPadding()->left ?? 0);
        $columns = $rect->columns - $left - ($style?->getBorder()->right ?? 0) - ($style?->getPadding()->right ?? 0);
        if ($columns < 1 || null === $value = $slider->valueAt($col - $rect->col - $left, $columns, $clamp)) {
            return false;
        }
        $slider->setValue($value, true);

        return true;
    }

    /**
     * Releases the pointer button pressed with press(): nothing is :active anymore.
     */
    public function release(): void
    {
        foreach ($this->pressed as $widget) {
            $widget->setState('active', false);
        }
        if ($this->pressed) {
            $this->pressed = [];
            $this->tui?->requestRender();
        }
    }

    /**
     * Clicks a cell: focuses the focusable widget there, and performs its
     * default action (presses a button, toggles a box or a <details>).
     *
     * The button is released, see press().
     *
     * @param bool $followLinks Whether a link of text with links is followed: focused and followed with a LinkEvent, for
     *                          an application that draws the view where nothing else follows it, like in a window; a
     *                          terminal follows the links it draws itself
     *
     * @return bool Whether the click landed on something it could act on
     */
    public function click(int $row, int $col, bool $followLinks = false): bool
    {
        $this->release();
        $this->lightDismiss($this->widgetAt($row, $col));
        // A link to a fragment of the page goes there
        if (null !== $fragment = Runtime::find($this->tui)?->getFragmentLinkAt(...$this->inFrame($row, $col))) {
            $this->navigate($fragment);

            return true;
        }
        // A ::scroll-button() scrolls its container a page, 85% of it like browsers
        if (null !== $control = Runtime::find($this->tui)?->getScrollControlAt(...$this->inFrame($row, $col))) {
            [$container, $action] = $control;
            // A ::scroll-marker scrolls to the child it stands for
            if (str_starts_with($action, 'marker:')) {
                if (null !== $child = ContainerState::of($container)->getScrollMarkers()[1][(int) substr($action, 7)][0] ?? null) {
                    Runtime::of($this->tui)->reveal($child);
                    // Drawn again, so that it scrolls there
                    $container->invalidate();
                    $this->tui->requestRender();
                }

                return true;
            }
            $vertical = \in_array($action, ['up', 'down'], true);
            $page = max(1, (int) floor(\Symfony\Component\HtmlRenderer\Html\ScrollControls::pageSize($vertical ? ContainerState::of($container)->getClientHeight() : ContainerState::of($container)->getClientWidth())));
            $sign = \in_array($action, ['up', 'left'], true) ? -1 : 1;
            $vertical ? ContainerState::of($container)->scrollBy($sign * $page) : ContainerState::of($container)->scrollByColumns($sign * $page);
            $this->tui->requestRender();

            return true;
        }
        for ($widget = $hit = $this->widgetAt($row, $col); null !== $widget; $widget = $widget === $this->root ? null : $widget->getParent()) {
            // The content of an open <details> is not its summary: a click there toggles nothing, like browsers, nor
            // activates a <label> around it, <details> being interactive content
            if ($widget instanceof CollapsibleWidget && $widget !== $hit) {
                return false;
            }
            // Nor is what of its box is below its summary, like its bottom border or padding: from where its content starts
            if ($widget instanceof CollapsibleWidget && $widget->isExpanded() && null !== ($content = $this->tui?->getWidgetRect($widget->getContent())) && $row >= $content->row) {
                return false;
            }
            // A label passes the click to its control, like browsers: a checkbox toggles
            if (isset($this->labelTargets[$widget])) {
                // A label for nothing does nothing
                $control = $this->getRoot()->findById($this->labelTargets[$widget]);

                return null !== $control && $control !== $widget && $this->activate($control);
            }
            // Text with links, or an image map: only its links do something, the terminal following the others
            if ($widget instanceof LinksInterface) {
                if ($followLinks && $widget instanceof LinkedTextWidget && null !== $link = $this->linkAt($widget, $row, $col)) {
                    $this->tui?->setFocus($widget);
                    $widget->focusLink($link);

                    return $widget->activate();
                }
                // An area of an image map, like a link
                if ($followLinks && $widget instanceof ImageMapWidget && null !== $area = $this->areaAt($widget, $row, $col)) {
                    $this->tui?->setFocus($widget);

                    return $widget->followArea($area);
                }
                continue;
            }
            if ($widget instanceof FocusableInterface || $widget instanceof ActivatableInterface) {
                return $this->activate($widget);
            }
        }

        return false;
    }

    /**
     * Goes to a fragment of the page, like following a link to #id: the element with that id becomes the target, which
     * :target matches, scrolled into view, and focused when it takes the focus. An empty fragment, or one that names
     * no element, leaves no target.
     *
     * A text fragment, like #:~:text=start,end or #id:~:text=prefix-,text,-suffix, draws the first match of each of its
     * texts as ::target-text, and is scrolled into view when no element is named.
     *
     * @return bool Whether an element or a text was found
     */
    public function navigate(string $fragment): bool
    {
        // A text fragment after the id, like #section:~:text=start,end: its first match drawn as ::target-text
        $fragment = ltrim(trim($fragment), '#');
        $directives = '';
        if (false !== $at = strpos($fragment, ':~:')) {
            [$fragment, $directives] = [substr($fragment, 0, $at), substr($fragment, $at + 3)];
        }
        $texts = $this->findTexts($directives);
        $this->styleSheet->setTargetTexts($texts);
        // Found again in the text built again by setInnerHtml(), see retarget()
        $this->targetTexts = array_map(fn (array $text): array => [...$text, $this->elementAround($text[0])], $texts);
        $this->tui?->requestRender();
        $id = rawurldecode($fragment);
        if ('' === $id && $texts) {
            $this->target?->setState('target', false);
            $this->target = null;
            Runtime::find($this->tui)?->reveal($texts[0][0]);

            return true;
        }
        $target = '' !== $id ? $this->getRoot()->findById($id) : null;
        if ($target !== $this->target) {
            $this->target?->setState('target', false);
            $target?->setState('target');
            $this->target = $target;
        }
        if (null === $target) {
            $this->tui?->requestRender();

            return false;
        }
        Runtime::find($this->tui)?->reveal($target);
        if ($target instanceof FocusableInterface && !$target->hasState('disabled')) {
            $this->tui?->setFocus($target);
        }

        return true;
    }

    public function getTarget(): ?AbstractWidget
    {
        return $this->target;
    }

    /**
     * Sets the links visited, by their href as written, which :visited matches, like the history of a browser the
     * application keeps; a link followed is added to them.
     *
     * @param iterable<string> $hrefs
     */
    public function setVisitedLinks(iterable $hrefs): void
    {
        $this->styleSheet->setVisitedLinks($hrefs);
        $this->tui?->requestRender();
    }

    /**
     * Marks a link as visited, by its href as written, which :visited then matches.
     */
    public function markVisited(string $href): void
    {
        $this->styleSheet->markVisited($href);
        $this->tui?->requestRender();
    }

    /**
     * @return list<string> The hrefs of the links visited
     */
    public function getVisitedLinks(): array
    {
        return $this->styleSheet->getVisitedLinks();
    }

    /**
     * @internal
     */
    public function setLabelTarget(AbstractWidget $label, string $controlId): void
    {
        $this->labelTargets[$label] = $controlId;
    }

    /**
     * The name a widget gave itself, before the page named it: what it is named again from when what named it goes.
     *
     * @internal for {@see HtmlBuilder}
     */
    public function setOwnName(AbstractWidget $widget, ?string $name): void
    {
        $this->ownNames[$widget] = [$name];
    }

    /**
     * @internal for {@see HtmlBuilder}
     */
    public function hasOwnName(AbstractWidget $widget): bool
    {
        return $this->ownNames->offsetExists($widget);
    }

    /**
     * @internal for {@see HtmlBuilder}
     */
    public function getOwnName(AbstractWidget $widget): ?string
    {
        return $this->ownNames[$widget][0] ?? null;
    }

    /**
     * @internal for {@see HtmlBuilder}
     *
     * @param list<\Closure(): void> $restores
     */
    public function setFormDefaults(AbstractWidget $control, array $restores): void
    {
        $this->formDefaults[$control] = $restores;
    }

    /**
     * @internal for {@see HtmlBuilder}
     *
     * @return list<\Closure(): void>|null
     */
    public function getFormDefaults(AbstractWidget $control): ?array
    {
        return $this->formDefaults[$control] ?? null;
    }

    /**
     * Returns the title of the element at a cell, or of its closest ancestor
     * that has one: what a tooltip shows.
     */
    public function titleAt(int $row, int $col): ?string
    {
        for ($widget = $this->widgetAt($row, $col); null !== $widget; $widget = $widget === $this->root ? null : $widget->getParent()) {
            $title = $this->styleSheet->getDescription($widget)[1]['title'] ?? null;
            if (null !== $title && '' !== $title = trim(StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($title)))) {
                return $title;
            }
        }

        return null;
    }

    /**
     * @internal
     */
    public function setRoot(AbstractWidget $root): void
    {
        $this->root = $root;
    }

    /**
     * @internal
     */
    public function setDocumentTitle(string $title): void
    {
        $this->documentTitle = $title;
    }

    /**
     * The text of the <title> of the document, "" without one.
     */
    public function getDocumentTitle(): string
    {
        return $this->documentTitle;
    }

    /**
     * @internal
     */
    public function addInitialTarget(AbstractWidget $widget): void
    {
        $this->initialTargets[] = $widget;
    }

    /**
     * @internal
     */
    public function setAutofocus(AbstractWidget $widget): void
    {
        $this->autofocus ??= $widget;
    }

    /**
     * @internal
     */
    public function setTabIndex(AbstractWidget $widget, int $tabIndex): void
    {
        $this->tabIndexes[$widget] = $tabIndex;
        // Built into the view mounted already, see setInnerHtml()
        $this->tui?->getFocusManager()->setTabIndex($widget, $tabIndex);
    }

    /**
     * @internal
     *
     * @throws InvalidArgumentException when the key is taken
     */
    public function setAccessKey(AbstractWidget $widget, string $key): void
    {
        $key = strtolower(mb_substr(trim($key), 0, 1));
        if (isset($this->accessKeys[$key]) && $this->accessKeys[$key] !== $widget) {
            throw new InvalidArgumentException(\sprintf('The access key "%s" is used twice.', $key));
        }
        $this->accessKeys[$key] = $widget;
    }

    /**
     * Mounts a nested document whose root is already in the tree: its styles and focus order.
     */
    private function attach(Tui $tui): void
    {
        $this->tui = $tui;
        $focusManager = $tui->getFocusManager();
        foreach ($this->tabIndexes as $widget => $tabIndex) {
            $focusManager->setTabIndex($widget, $tabIndex);
        }
        $this->provideStyles($tui);
        foreach ($this->frames as $frame) {
            $frame->attach($tui);
        }
        // Its keys, links, animations, popovers and dialogs, like those of the page; the focus stays where it is
        $this->listen($tui);
    }

    /**
     * Listens to the Tui for what the document does: its access keys, its animations, its links to fragments, its
     * interest targets, Escape for its popovers and dialogs, and the keys that scroll it.
     */
    private function listen(Tui $tui): void
    {
        // The listeners of this mount, those of an earlier one still dispatched to doing nothing
        $generation = ++$this->generation;
        // The keys of the view when it is pressed, those of content added after the mount too
        $this->listenTo($tui, function (InputEvent $event) use ($tui, $generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation || !$this->accessKeys) {
                return;
            }
            $bindings = [];
            foreach ($this->accessKeys as $key => $widget) {
                $bindings['access_'.$key] = ['alt+'.$key];
            }
            $keybindings = new Keybindings($bindings);
            foreach ($this->accessKeys as $key => $widget) {
                if ($widget instanceof FocusableInterface && $keybindings->matches($event->getData(), 'access_'.$key)) {
                    $tui->setFocus($widget);
                    $event->stopPropagation();

                    return;
                }
            }
        });
        // While CSS animations run, frames are drawn about 30 times a second; none when they end
        // Once for the style sheet, whose views share it, on the Tui mounted last; not holding the view, which the style
        // sheet may outlive
        $this->animateOn($tui);
        // A link followed is visited, :visited matching it; one to a fragment goes there
        $this->listenTo($tui, function (LinkEvent $event) use ($generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
            if (!$this->owns($event->getTarget())) {
                return;
            }
            $this->markVisited($event->getHref());
            if (str_starts_with($event->getHref(), '#')) {
                $this->navigate($event->getHref());
            }
        });
        // A form of method dialog closes the dialog it is in, like in a browser
        $this->listenTo($tui, function (FormSubmitEvent $event) use ($generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
            if ('dialog' !== $event->getMethod() || !$this->owns($event->getTarget())) {
                return;
            }
            for ($widget = $event->getTarget(); null !== $widget; $widget = $widget->getParent()) {
                if ('dialog' === ($this->styleSheet->getDescription($widget)[0] ?? null)) {
                    // Its return value is the value of the button that submitted it
                    $submitter = $event->getSubmitter();
                    $this->close($widget, null !== $submitter ? $this->styleSheet->getDescription($submitter)[1]['value'] ?? '' : null);

                    return;
                }
            }
        });
        // The key that moves the focus back, Shift+F6 unless the Tui binds another one, like the focus manager
        $this->listenTo($tui, function (InputEvent $event) use ($generation): void {
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
            $global = $this->root?->getContext()?->keybindings();
            $this->focusingBack = (new Keybindings(array_merge(['focus_previous' => ['shift+f6']], $global?->all() ?? []), $global?->getParser()))->matches($event->getData(), 'focus_previous') ? \WeakReference::create($event) : null;
        });
        // Focusing an element with interestfor shows its popover, like pointing at it
        $this->listenTo($tui, function (FocusEvent $event) use ($generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
            // Coming back into text with links, or an image map, goes to its last link, like Shift+Tab in a browser
            // Only while that key is handled: one that moved no focus leaves the next focus alone
            $target = $event->getTarget();
            if (null !== $this->focusingBack?->get() && $target instanceof LinksInterface && $this->owns($target)) {
                $target->focusLastLink();
            }
            $this->focusingBack = null;
            $this->updateInterest();
        });
        // A key that activates the widget with the focus, like Enter or Space on a button: it matches :active a moment,
        // terminals not telling when a key is released
        $activate = new Keybindings(['activate' => [Key::ENTER, Key::SPACE]]);
        $this->listenTo($tui, function (InputEvent $event) use ($activate, $tui, $generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
            $focus = $tui->getFocus();
            if (!$focus instanceof ActivatableInterface || !$this->owns($focus) || !$activate->matches($event->getData(), 'activate')) {
                return;
            }
            $this->release();
            for ($current = $focus; null !== $current; $current = $current === $this->root ? null : $current->getParent()) {
                $this->pressed[] = $current->setState('active');
            }
            $tui->requestRender();
            // A press before the release of the previous one keeps the moment going
            if (null !== $timer = $this->timers['press'] ?? null) {
                $tui->cancelInterval($timer);
            }
            $this->timers['press'] = $tui->scheduleInterval(function () use ($tui): void {
                $tui->cancelInterval($this->timers['press']);
                unset($this->timers['press']);
                $this->release();
            }, 0.15);
        });
        // Escape closes the last modal dialog opened, like in a browser
        $escape = new Keybindings(['close' => [Key::ESCAPE]]);
        $this->listenTo($tui, function (InputEvent $event) use ($escape, $generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
            // The last auto popover first, like the top layer
            $popover = null;
            foreach ($this->openPopovers as $open) {
                if (\in_array($this->popovers[$open] ?? null, ['auto', 'hint'], true)) {
                    $popover = $open;
                }
            }
            if (null !== $popover && $escape->matches($event->getData(), 'close')) {
                $this->hidePopover($popover);
                $event->stopPropagation();

                return;
            }
            // The last modal dialog, else the last open one; unless its closedby says Escape does not close it
            $last = null;
            foreach ($this->modals as $dialog => $_) {
                $last = $dialog;
            }
            if (null === $last && $open = $this->openDialogs()) {
                $last = $open[\count($open) - 1];
            }
            if (null !== $last && $escape->matches($event->getData(), 'close') && 'none' !== $this->closedBy($last)) {
                $this->requestClose($last);
                $event->stopPropagation();
            }
        });
        // Page Up and Page Down scroll the scroll container around the focus, unless the focused widget pages itself;
        // Left and Right scroll the one around it sideways, unless the focused widget moves with them
        $paging = new Keybindings(['page_up' => [Key::PAGE_UP], 'page_down' => [Key::PAGE_DOWN], 'left' => [Key::LEFT], 'right' => [Key::RIGHT]]);
        $this->listenTo($tui, function (InputEvent $event) use ($paging, $tui, $generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
            $direction = $paging->matches($event->getData(), 'page_up') ? -1 : ($paging->matches($event->getData(), 'page_down') ? 1 : 0);
            if (0 !== $direction && $this->scrollPage($tui->getFocus(), $event->getData(), $direction)) {
                $tui->requestRender();
                $event->stopPropagation();
            }
            $sideways = $paging->matches($event->getData(), 'left') ? -1 : ($paging->matches($event->getData(), 'right') ? 1 : 0);
            if (0 !== $sideways && $this->scrollSideways($tui->getFocus(), $event->getData(), $sideways)) {
                $tui->requestRender();
                $event->stopPropagation();
            }
        });
    }

    /**
     * Adds a listener to the Tui, which unmount() removes.
     */
    private function listenTo(Tui $tui, \Closure $listener): void
    {
        $this->listeners[] = $listener;
        $tui->addListener($listener);
    }

    /**
     * Removes what attach() or mount() added to the Tui, of this document and those nested in it.
     */
    private function detach(Tui $tui): void
    {
        foreach ($this->frames as $frame) {
            $frame->detach($tui);
        }
        foreach ($this->listeners as $listener) {
            $tui->removeListener($listener);
        }
        $this->listeners = [];
        foreach ($this->timers as $timer) {
            $tui->cancelInterval($timer);
        }
        $this->timers = [];
        $this->release();
        // The styles stay while another view mounted there shares them
        $mounted = self::$mounted[$tui] ?? null;
        $count = ($mounted[$this->styleSheet] ?? 1) - 1;
        if (null !== $mounted && $count > 0) {
            $mounted[$this->styleSheet] = $count;
            $tui->requestRender();
        } else {
            unset($mounted[$this->styleSheet]);
            $this->runtime?->removeStyleProvider($this->styleSheet);
            // Its animations drawn there no more, nor anywhere it is not mounted
            if (null !== $animation = self::$animations[$this->styleSheet] ?? null) {
                if (null !== $timer = $animation->timers[$tui] ?? null) {
                    $tui->cancelInterval($timer);
                    unset($animation->timers[$tui]);
                }
                if (!$this->sheetMounted()) {
                    $this->styleSheet->setAnimationHandlers(null);
                    unset(self::$animations[$this->styleSheet]);
                }
            }
        }
        $this->tui = null;
        $this->runtime = null;
    }

    /**
     * Draws the frames of the animations of the style sheet on a Tui, the one its views were mounted on last; scheduled
     * at once when they run already, like on a page mounted while another of the style sheet animated.
     */
    private function animateOn(Tui $tui): void
    {
        $styleSheet = $this->styleSheet;
        self::$animations ??= new \WeakMap();
        if (null === $animation = self::$animations[$styleSheet] ?? null) {
            $animation = self::$animations[$styleSheet] = new \stdClass();
            $animation->timers = new \WeakMap();
            // On each Tui the style sheet is mounted on, an interval while its animations run
            $animation->schedule = static function () use ($animation, $styleSheet): void {
                foreach (self::$mounted ?? [] as $on => $sheets) {
                    if (!isset($sheets[$styleSheet]) || isset($animation->timers[$on])) {
                        continue;
                    }
                    $animation->timers[$on] = $on->scheduleInterval(static function () use ($animation, $styleSheet, $on): void {
                        if ($styleSheet->isAnimating() && isset(self::$mounted[$on][$styleSheet])) {
                            $on->requestRender();
                        } elseif (null !== $timer = $animation->timers[$on] ?? null) {
                            $on->cancelInterval($timer);
                            unset($animation->timers[$on]);
                        }
                    }, 1 / 30);
                }
            };
        }
        $styleSheet->setAnimationHandlers($animation->schedule);
        if ($styleSheet->isAnimating()) {
            ($animation->schedule)();
        }
    }

    /**
     * Whether the style sheet of the view is mounted on some Tui, by this view or another.
     */
    private function sheetMounted(): bool
    {
        foreach (self::$mounted ?? [] as $sheets) {
            if (isset($sheets[$this->styleSheet])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds the styles of the view to a Tui, counting the views that share them there.
     */
    private function provideStyles(Tui $tui): void
    {
        self::$mounted ??= new \WeakMap();
        $mounted = self::$mounted[$tui] ?? null;
        if (null === $mounted) {
            /** @var \WeakMap<CssStyleSheet, int> $mounted */
            $mounted = new \WeakMap();
            self::$mounted[$tui] = $mounted;
        }
        $mounted[$this->styleSheet] = ($mounted[$this->styleSheet] ?? 0) + 1;
        ($this->runtime = Runtime::of($tui))->addStyleProvider($this->styleSheet);
        // The styles follow the view to another HTML renderer selected before the start
        $this->listenTo($tui, function (RendererChangeEvent $event) use ($tui): void {
            if ($tui !== $this->tui) {
                return;
            }
            $this->runtime?->removeStyleProvider($this->styleSheet);
            $this->runtime = Runtime::find($tui);
            $this->runtime?->addStyleProvider($this->styleSheet);
        });
    }

    /**
     * Dispatches a BeforeToggleEvent on a dialog about to open: whether it still opens, unless stopped or opened by a
     * listener.
     */
    private function beforeOpening(AbstractWidget $dialog): bool
    {
        $dialog->dispatch($before = new BeforeToggleEvent($dialog, 'closed', 'open'));

        return !$before->isPropagationStopped() && !$dialog->hasState('open');
    }

    private function focusFirstIn(AbstractWidget $dialog): void
    {
        $focusable = null;
        $find = static function (AbstractWidget $widget) use (&$find, &$focusable): void {
            foreach ($widget instanceof ParentInterface ? $widget->all() : [] as $child) {
                if (null === $focusable && $child instanceof FocusableInterface) {
                    $focusable = $child;
                }
                $find($child);
            }
        };
        $find($dialog);
        if (null !== $focusable) {
            $this->tui?->setFocus($focusable);
        }
    }

    /**
     * Shows the popovers of the elements with interestfor that are pointed at or focused, anchored to them, and hides
     * those nothing keeps an interest in anymore: neither their element nor themselves are pointed at or focused.
     */
    private function updateInterest(): void
    {
        if (0 === \count($this->interestTargets)) {
            return;
        }
        $focus = $this->tui?->getFocus();
        $wanted = [];
        foreach ($this->interestTargets as $invoker => $targetId) {
            $interested = \in_array($invoker, $this->hovered, true) || null !== $focus && self::isInside($invoker, $focus);
            if (!$interested || null === $target = $this->getRoot()->findById($targetId)) {
                continue;
            }
            $wanted[spl_object_id($target)] = $target;
            if (!\in_array($target, $this->openPopovers, true)) {
                $this->anchor($target, $invoker);
                $this->showPopover($target);
            }
        }
        foreach ($this->interestShown as $shown) {
            // Pointing at the popover itself keeps it open, to read or click it
            if (!isset($wanted[spl_object_id($shown)]) && !\in_array($shown, $this->hovered, true)) {
                $this->hidePopover($shown);
            }
        }
        // Those still open, once each
        $kept = [];
        foreach ([...array_values($wanted), ...$this->interestShown] as $popover) {
            if (\in_array($popover, $this->openPopovers, true)) {
                $kept[spl_object_id($popover)] = $popover;
            }
        }
        $this->interestShown = array_values($kept);
    }

    /**
     * A click outside the auto popovers open closes them, but those the clicked button opens.
     */
    /**
     * How a dialog closes, like its closedby attribute: any (Escape or a click outside), closerequest (Escape) or none;
     * by default closerequest for a modal one, none for the others.
     */
    private function closedBy(AbstractWidget $dialog): string
    {
        $value = strtolower(trim($this->styleSheet->getDescription($dialog)[1]['closedby'] ?? ''));

        return \in_array($value, ['any', 'closerequest', 'none'], true) ? $value : ($dialog->hasState('modal') ? 'closerequest' : 'none');
    }

    /**
     * The dialogs open, modal or not, in document order.
     *
     * @return list<AbstractWidget>
     */
    private function openDialogs(): array
    {
        $open = [];
        $walk = function (AbstractWidget $widget) use (&$walk, &$open): void {
            if ($widget->hasState('open') && 'dialog' === ($this->styleSheet->getDescription($widget)[0] ?? null)) {
                $open[] = $widget;
            }
            if ($widget instanceof ParentInterface) {
                foreach ($widget->all() as $child) {
                    $walk($child);
                }
            }
        };
        $walk($this->getRoot());

        return $open;
    }

    private function lightDismiss(?AbstractWidget $clicked): void
    {
        // A dialog that closes on any click outside it, like closedby="any"
        foreach (array_reverse($this->openDialogs()) as $dialog) {
            if ('any' === $this->closedBy($dialog) && (null === $clicked || !self::isInside($dialog, $clicked))) {
                $this->requestClose($dialog);
            }
        }
        $invoked = null;
        for ($widget = $clicked; null !== $widget; $widget = $widget->getParent()) {
            $invoked ??= $this->invokers[$widget] ?? null;
        }
        foreach (array_reverse($this->openPopovers) as $open) {
            if (!\in_array($this->popovers[$open] ?? null, ['auto', 'hint'], true) || null !== $clicked && self::isInside($open, $clicked) || null !== $invoked && $open->getId() === $invoked) {
                continue;
            }
            $this->hidePopover($open);
        }
    }

    private static function isInside(AbstractWidget $ancestor, AbstractWidget $widget): bool
    {
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scrolls by a page the closest scroll container around the focus that can go that way, or the first one of the
     * view when nothing in it is focused.
     */
    private function scrollPage(?AbstractWidget $focused, string $data, int $direction): bool
    {
        if (self::usesKey($focused, $data)) {
            return false;
        }
        $start = null !== $focused && $this->owns($focused) ? $focused : $this->firstScrollContainer($this->getRoot());
        for ($widget = $start; null !== $widget; $widget = $widget === $this->getRoot() ? null : $widget->getParent()) {
            if ($widget instanceof ContainerWidget && ContainerState::of($widget)->getClientHeight() > 0) {
                $before = ContainerState::of($widget)->getScrollTop();
                if ($before !== ContainerState::of(ContainerState::of($widget)->scrollBy($direction * max(1, ContainerState::of($widget)->getClientHeight() - 1)))->getScrollTop()) {
                    return true;
                }
                if (ContainerState::of($widget)->isOverscrollContained()) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Scrolls by a few columns the closest scroll container around the focus that can go that way sideways, or the
     * first one of the view when nothing in it is focused.
     */
    private function scrollSideways(?AbstractWidget $focused, string $data, int $direction): bool
    {
        if (self::usesKey($focused, $data)) {
            return false;
        }
        $start = null !== $focused && $this->owns($focused) ? $focused : $this->firstScrollContainer($this->getRoot(), true);
        for ($widget = $start; null !== $widget; $widget = $widget === $this->getRoot() ? null : $widget->getParent()) {
            if ($widget instanceof ContainerWidget && ContainerState::of($widget)->getClientWidth() > 0) {
                $before = ContainerState::of($widget)->getScrollLeft();
                // A few columns, like an arrow key scrolls a few pixels in a browser
                if ($before !== ContainerState::of(ContainerState::of($widget)->scrollByColumns($direction * max(1, min(4, intdiv(ContainerState::of($widget)->getClientWidth(), 4)))))->getScrollLeft()) {
                    return true;
                }
                if (ContainerState::of($widget)->isOverscrollContainedSideways()) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Whether the focused widget does something with a key, which then does not scroll.
     */
    private static function usesKey(?AbstractWidget $focused, string $data): bool
    {
        if (null !== $focused && method_exists($focused, 'getKeybindings')) {
            $keybindings = $focused->getKeybindings();
            foreach ($keybindings->all() as $action => $_) {
                if ($keybindings->matches($data, $action)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function owns(AbstractWidget $widget): bool
    {
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($current === $this->getRoot()) {
                return true;
            }
        }

        return false;
    }

    private function firstScrollContainer(AbstractWidget $widget, bool $sideways = false): ?ContainerWidget
    {
        if ($widget instanceof ContainerWidget && ($sideways ? ContainerState::of($widget)->getClientWidth() : ContainerState::of($widget)->getClientHeight()) > 0) {
            return $widget;
        }
        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                if (null !== $found = $this->firstScrollContainer($child, $sideways)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * The element the last fragment navigated to, see navigate().
     */
    /**
     * The text blocks the text directives of a fragment go to, each with the pattern of its text: the first block
     * whose text matches, in the order of the page, its prefix and suffix around it, whole words, the case not mattering.
     *
     * @return list<array{RichTextWidget, string}>
     */
    private function findTexts(string $directives): array
    {
        $patterns = [];
        foreach (explode('&', $directives) as $directive) {
            if (!str_starts_with($directive, 'text=') || '' === $directive = substr($directive, 5)) {
                continue;
            }
            $parts = array_map(rawurldecode(...), explode(',', $directive));
            $prefix = str_ends_with($parts[0], '-') && \count($parts) > 1 ? substr(array_shift($parts), 0, -1) : null;
            $suffix = str_starts_with($parts[\count($parts) - 1], '-') && \count($parts) > 1 ? substr(array_pop($parts), 1) : null;
            if (!\in_array(\count($parts), [1, 2], true) || \in_array('', [$parts[0], $parts[1] ?? 'x', $prefix ?? 'x', $suffix ?? 'x'], true)) {
                continue;
            }
            $words = static fn (string $text): string => str_replace(' ', '\s+', preg_quote(trim((string) preg_replace('/\s+/u', ' ', $text)), '/'));
            $target = '(?<![\p{L}\p{N}_])'.$words($parts[0]).(isset($parts[1]) ? '.*?'.$words($parts[1]) : '').'(?![\p{L}\p{N}_])';
            $patterns[] = '/'.(null !== $prefix ? $words($prefix).'\s*' : '').'('.$target.')'.(null !== $suffix ? '\s*'.$words($suffix) : '').'/isu';
            if (\count($patterns) >= 16) {
                break;
            }
        }
        if (!$patterns) {
            return [];
        }
        $found = [];
        $walk = static function (AbstractWidget $widget) use (&$walk, &$patterns, &$found): void {
            if ($widget instanceof RichTextWidget) {
                $source = $widget->getSourceText();
                foreach ($patterns as $i => $pattern) {
                    if (preg_match($pattern, $source)) {
                        $found[] = [$widget, $pattern];
                        unset($patterns[$i]);
                    }
                }
            }
            if ($widget instanceof ParentInterface) {
                foreach ($widget->all() as $child) {
                    if ($patterns) {
                        $walk($child);
                    }
                }
            }
        };
        $walk($this->getRoot());

        return $found;
    }

    /**
     * A cell where Tui::getWidgetRect() has the widgets, in the frame the renderer drew: a renderer that decorates it
     * may show that frame elsewhere, like a window showing a part of it, and the links of the frame are where it drew
     * them.
     *
     * @return array{int, int}
     */
    private function inFrame(int $row, int $col): array
    {
        $shown = $this->tui?->getWidgetRect($this->getRoot());
        $drawn = Runtime::renderer($this->tui?->getRenderer())?->getWidgetRect($this->getRoot());

        return null === $shown || null === $drawn ? [$row, $col] : [$row - $shown->row + $drawn->row, $col - $shown->col + $drawn->col];
    }

    /**
     * The index of the link of a text drawn at a cell, null when none of its links is there.
     */
    private function linkAt(RichTextWidget $widget, int $row, int $col): ?int
    {
        if (null !== ($id = Runtime::find($this->tui)?->getLinkAt(...$this->inFrame($row, $col))) && preg_match('/^tui-link-(\d+)-(\d+)$/', $id, $m) && (int) $m[1] === spl_object_id($widget)) {
            return (int) $m[2];
        }

        return null;
    }

    /**
     * The area of an image map drawn at a cell, by its index.
     */
    private function areaAt(ImageMapWidget $widget, int $row, int $col): ?int
    {
        $id = Runtime::find($this->tui)?->getLinkAt(...$this->inFrame($row, $col));

        return null !== $id ? $widget->getAreaByLinkId($id) : null;
    }

    private function activate(AbstractWidget $widget): bool
    {
        // A dropdown, like through its label, takes the focus on its field without opening, like a browser's
        if ($widget instanceof DropdownWidget) {
            if ($widget->hasState('disabled')) {
                return false;
            }
            $this->tui?->setFocus($widget->getField());

            return true;
        }
        if (!$widget instanceof FocusableInterface && !$widget instanceof ActivatableInterface || $widget->hasState('disabled')) {
            return false;
        }
        if ($widget instanceof FocusableInterface) {
            $this->tui?->setFocus($widget);
        }

        return !$widget instanceof ActivatableInterface || $widget->activate() || $widget instanceof FocusableInterface;
    }

    private function overlayAt(AbstractWidget $root, int $row, int $col): ?AbstractWidget
    {
        $overlays = [];
        $style = static fn (AbstractWidget $widget): Style => StyleBridge::resolve($widget->getContext(), $widget) ?? new Style();
        $indexes = [];
        $collect = static function (AbstractWidget $widget) use (&$collect, &$overlays, &$indexes, $root, $style): void {
            foreach ($widget instanceof ParentInterface ? $widget->all() : [] as $child) {
                // The absolute and fixed elements, and the relative ones of a positive z-index, drawn with them, in the
                // order they are drawn: one under the flow does not take the pointer, which the flow takes
                if (StackingOrder::isRaised($style($child)->getPosition()) && null !== ($key = StackingOrder::key($child, $root, $style, $indexes)) && true !== $key['under']) {
                    $overlays[] = [$key, $child];
                }
                $collect($child);
            }
        };
        $collect($root);
        usort($overlays, static fn (array $a, array $b) => StackingOrder::compare($b[0], $a[0]));
        foreach ($overlays as [, $overlay]) {
            if ($this->contains($overlay, $row, $col)) {
                return $overlay;
            }
        }

        return null;
    }

    private function contains(AbstractWidget $widget, int $row, int $col): bool
    {
        // A widget hidden by CSS keeps the rect of the frame it was last drawn in
        if (null === $context = $widget->getContext()) {
            return false;
        }
        // Hidden, or inside a hidden ancestor, it is not drawn
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            $style = StyleBridge::resolve($context, $current);
            if ($style->getHidden() || $style->getInvisible() || $current !== $widget && $style->getContentHidden()) {
                return false;
            }
        }

        return $this->tui?->getWidgetRect($widget)?->contains($row, $col) ?? false;
    }

    private function flowAt(AbstractWidget $widget, int $row, int $col): ?AbstractWidget
    {
        if (!$this->contains($widget, $row, $col)) {
            return null;
        }
        $children = $widget instanceof ParentInterface ? $widget->all() : [];
        if (null !== StyleBridge::resolve($widget->getContext(), $widget)?->getDirection()) {
            usort($children, static fn (AbstractWidget $a, AbstractWidget $b): int => (StyleBridge::resolve($a->getContext(), $a)?->getOrder() ?? 0) <=> (StyleBridge::resolve($b->getContext(), $b)?->getOrder() ?? 0));
        }
        foreach (array_reverse($children) as $child) {
            $style = StyleBridge::resolve($child->getContext(), $child);
            if ($style?->getPosition()?->isOutOfFlow() || ($style?->getPosition()->zIndex ?? 0) < 0) {
                continue;
            }
            if (null !== $hit = $this->flowAt($child, $row, $col)) {
                return $hit;
            }
        }

        return $widget;
    }
}
