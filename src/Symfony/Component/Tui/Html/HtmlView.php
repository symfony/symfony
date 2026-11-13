<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html;

use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Event\BeforeToggleEvent;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\CloseEvent;
use Symfony\Component\Tui\Event\CommandEvent;
use Symfony\Component\Tui\Event\FocusEvent;
use Symfony\Component\Tui\Event\FormSubmitEvent;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\LinkEvent;
use Symfony\Component\Tui\Event\ToggleEvent;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Exception\LogicException;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\StackingOrder;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ActivatableInterface;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\DropdownWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\LinksInterface;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\RichTextWidget;
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

    /** @var list<AbstractWidget> The widget under the pointer and its ancestors */
    private array $hovered = [];

    /** @var \WeakReference<RichTextWidget>|null The text whose link is under the pointer */
    private ?\WeakReference $hoveredLink = null;

    /** @var list<AbstractWidget> The widget a pointer button is pressed on and its ancestors, which :active matches */
    private array $pressed = [];

    /** The element the last fragment navigated to, which :target matches */
    private ?AbstractWidget $target = null;
    private ?AbstractWidget $autofocus = null;

    /** @var list<AbstractWidget> What may have scroll-initial-target, in document order */
    private array $initialTargets = [];

    /** @var \WeakMap<AbstractWidget, int> */
    private \WeakMap $tabIndexes;

    /** @var \WeakMap<AbstractWidget, string> The id of the control of each label */
    private \WeakMap $labelTargets;

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
    /** @var \WeakMap<CssStyleSheet, \stdClass>|null The animations of each style sheet: the interval drawing their frames on each Tui it is mounted on and what schedules them, shared by the views of the style sheet, see animateOn() */
    private static ?\WeakMap $animations = null;

    /**
     * @internal built by {@see HtmlBuilder}
     */
    public function __construct(
        private readonly CssStyleSheet $styleSheet,
    ) {
        $this->modals = new \WeakMap();
        $this->returnValues = new \WeakMap();
        $this->popovers = new \WeakMap();
        $this->invokers = new \WeakMap();
        $this->interestTargets = new \WeakMap();
        $this->tabIndexes = new \WeakMap();
        $this->labelTargets = new \WeakMap();
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
        $body = $root instanceof ContainerWidget ? ($root->all()[0] ?? null) : null;

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
        if ($this->accessKeys) {
            $bindings = [];
            foreach ($this->accessKeys as $key => $widget) {
                $bindings['access_'.$key] = ['alt+'.$key];
            }
            $keybindings = new Keybindings($bindings);
            $this->listenTo($tui, function (InputEvent $event) use ($keybindings, $tui, $generation): void {
                // Not once unmounted, like during the event an application listener left the page on, nor mounted since
                if (null === $this->tui || $generation !== $this->generation) {
                    return;
                }
                foreach ($this->accessKeys as $key => $widget) {
                    if ($widget instanceof FocusableInterface && $keybindings->matches($event->getData(), 'access_'.$key)) {
                        $tui->setFocus($widget);
                        $event->stopPropagation();

                        return;
                    }
                }
            });
        }

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

        // Focusing an element with interestfor shows its popover, like pointing at it
        $this->listenTo($tui, function (FocusEvent $event) use ($generation): void {
            // Not once unmounted, like during the event an application listener left the page on, nor mounted since
            if (null === $this->tui || $generation !== $this->generation) {
                return;
            }
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
        // Mounted already, or attached as a document nested in another: from there first, counted once
        if (null !== $this->tui && !$this->isMounted) {
            $this->detach($this->tui);
        }
        $this->unmount();
        // A page of the whole screen replaces the one before it, like a browser going to another page: unmounted, not
        // kept behind it
        if (null !== $parent) {
            self::$parts ??= new \WeakMap();
            self::$parts[$tui] ??= new \WeakMap();
            self::$parts[$tui][$this] = true;
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
            $tui->reveal($target);
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
        if (isset(self::$parts[$tui])) {
            unset(self::$parts[$tui][$this]);
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
            $tui->removeStyleProvider($this->styleSheet);
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
        $mounted = self::$mounted[$tui] ??= new \WeakMap();
        $mounted[$this->styleSheet] = ($mounted[$this->styleSheet] ?? 0) + 1;
        $tui->addStyleProvider($this->styleSheet);
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
        $dialog->dispatchOn(new ToggleEvent($dialog, 'closed', 'open'));
    }

    /**
     * Dispatches a BeforeToggleEvent on a dialog about to open: whether it still opens, unless stopped or opened by a
     * listener.
     */
    private function beforeOpening(AbstractWidget $dialog): bool
    {
        $dialog->dispatchOn($before = new BeforeToggleEvent($dialog, 'closed', 'open'));

        return !$before->isPropagationStopped() && !$dialog->hasState('open');
    }

    /**
     * Opens a dialog as a modal, like HTMLDialogElement::showModal(): centered over the page, which is inert until it is
     * closed; the focus goes to its first focusable widget, and Escape closes it.
     */
    public function showModal(AbstractWidget $dialog): void
    {
        // One open in the page becomes a modal, already open: no toggle events
        $wasOpen = $dialog->hasState('open');
        if (isset($this->modals[$dialog]) || (!$wasOpen && !$this->beforeOpening($dialog))) {
            return;
        }
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
        $this->modals[$dialog] = [$inerted, $this->tui?->getFocus()];
        $dialog->setState('open')->setState('modal');
        $this->focusFirstIn($dialog);
        $this->tui?->requestRender();
        if (!$wasOpen) {
            $dialog->dispatchOn(new ToggleEvent($dialog, 'closed', 'open'));
        }
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
            $interested = \in_array($invoker, $this->hovered, true) || (null !== $focus && self::isInside($invoker, $focus));
            if (!$interested || null === $target = $this->getRoot()->findById($targetId)) {
                continue;
            }
            $wanted[spl_object_id($target)] = $target;
            if (!\in_array($target, $this->openPopovers, true)) {
                $this->tui?->setImplicitAnchor($target, $invoker);
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
        $popover->dispatchOn($before = new BeforeToggleEvent($popover, 'closed', 'open'));
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
        $popover->dispatchOn(new ToggleEvent($popover, 'closed', 'open'));
    }

    /**
     * Hides a popover, like HTMLElement::hidePopover(), and those open inside it.
     */
    public function hidePopover(AbstractWidget $popover): void
    {
        if (!\in_array($popover, $this->openPopovers, true)) {
            return;
        }
        $popover->dispatchOn(new BeforeToggleEvent($popover, 'open', 'closed'));
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
        $popover->dispatchOn(new ToggleEvent($popover, 'open', 'closed'));
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
        $target->dispatchOn($event = new CommandEvent($target, $command, $invoker));
        if ($custom || $event->isPropagationStopped()) {
            return;
        }
        // The invoker of a popover is its implicit anchor, like in HTML
        if (null !== $invoker && str_ends_with($command, '-popover')) {
            $this->tui?->setImplicitAnchor($target, $invoker);
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
            if (!\in_array($this->popovers[$open] ?? null, ['auto', 'hint'], true) || (null !== $clicked && self::isInside($open, $clicked)) || (null !== $invoked && $open->getId() === $invoked)) {
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
     * Closes a dialog, like HTMLDialogElement::close(): a modal one gives the page and the focus back; a CloseEvent is
     * dispatched on it, with its return value, which $returnValue sets.
     */
    public function close(AbstractWidget $dialog, ?string $returnValue = null): void
    {
        if (!$dialog->hasState('open')) {
            return;
        }
        $dialog->dispatchOn(new BeforeToggleEvent($dialog, 'open', 'closed'));
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
        $dialog->dispatchOn(new ToggleEvent($dialog, 'open', 'closed'));
        $dialog->dispatchOn(new CloseEvent($dialog, $this->getReturnValue($dialog)));
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
        $dialog->dispatchOn($event = new CancelEvent($dialog));
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
        return $this->returnValues[$dialog] ?? '';
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

        // An inert subtree takes no pointer: what is behind it does
        // interactivity: inert, inherited: up to the closest ancestor that is not
        while (true === $widget->getContext()?->resolveStyle($widget)->getInert() && $widget !== $this->getRoot() && null !== $parent = $widget->getParent()) {
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
                if (0 !== $rows && $widget->getClientHeight() > 0) {
                    $before = $widget->getScrollTop();
                    if ($before !== $widget->scrollBy($rows)->getScrollTop()) {
                        $scrolled = true;
                        $rows = 0;
                    } elseif ($widget->isOverscrollContained()) {
                        // overscroll-behavior: contain, the containers around it do not scroll
                        $rows = 0;
                    }
                }
                if (0 !== $columns && $widget->getClientWidth() > 0) {
                    $before = $widget->getScrollLeft();
                    if ($before !== $widget->scrollByColumns($columns)->getScrollLeft()) {
                        $scrolled = true;
                        $columns = 0;
                    } elseif ($widget->isOverscrollContainedSideways()) {
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
            if ($widget instanceof ContainerWidget && $widget->getClientHeight() > 0) {
                $before = $widget->getScrollTop();
                if ($before !== $widget->scrollBy($direction * max(1, $widget->getClientHeight() - 1))->getScrollTop()) {
                    return true;
                }
                if ($widget->isOverscrollContained()) {
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
            if ($widget instanceof ContainerWidget && $widget->getClientWidth() > 0) {
                $before = $widget->getScrollLeft();
                // A few columns, like an arrow key scrolls a few pixels in a browser
                if ($before !== $widget->scrollByColumns($direction * max(1, min(4, intdiv($widget->getClientWidth(), 4))))->getScrollLeft()) {
                    return true;
                }
                if ($widget->isOverscrollContainedSideways()) {
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
        if ($widget instanceof ContainerWidget && ($sideways ? $widget->getClientWidth() : $widget->getClientHeight()) > 0) {
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

        // Over a link of a text, a pointer, like the a[href] { cursor: pointer } of browsers
        if ($widget instanceof RichTextWidget && str_starts_with($this->tui?->getLinkAt($row, $col) ?? '', 'tui-link-'.spl_object_id($widget).'-')) {
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
        $link = null;
        if ($widget instanceof RichTextWidget && null !== ($id = $this->tui?->getLinkAt($row, $col)) && preg_match('/^tui-link-(\d+)-(\d+)$/', $id, $m) && (int) $m[1] === spl_object_id($widget)) {
            $link = (int) $m[2];
        }
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

        return $widget;
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
     * @return bool Whether the click landed on something it could act on
     */
    public function click(int $row, int $col): bool
    {
        $this->release();
        $this->lightDismiss($this->widgetAt($row, $col));

        // A link to a fragment of the page goes there
        if (null !== $fragment = $this->tui?->getFragmentLinkAt($row, $col)) {
            $this->navigate($fragment);

            return true;
        }
        // A ::scroll-button() scrolls its container a page, 85% of it like browsers
        if (null !== $control = $this->tui?->getScrollControlAt($row, $col)) {
            [$container, $action] = $control;
            // A ::scroll-marker scrolls to the child it stands for
            if (str_starts_with($action, 'marker:')) {
                if (null !== $child = $container->getScrollMarkers()[1][(int) substr($action, 7)][0] ?? null) {
                    $this->tui?->reveal($child);
                    // Drawn again, so that it scrolls there
                    $container->invalidate();
                    $this->tui?->requestRender();
                }

                return true;
            }
            $vertical = \in_array($action, ['up', 'down'], true);
            $page = max(1, (int) floor(0.85 * ($vertical ? $container->getClientHeight() : $container->getClientWidth())));
            $sign = \in_array($action, ['up', 'left'], true) ? -1 : 1;
            $vertical ? $container->scrollBy($sign * $page) : $container->scrollByColumns($sign * $page);
            $this->tui?->requestRender();

            return true;
        }

        for ($widget = $this->widgetAt($row, $col); null !== $widget; $widget = $widget === $this->root ? null : $widget->getParent()) {
            // A label passes the click to its control, like browsers: a checkbox toggles
            if (isset($this->labelTargets[$widget])) {
                // A label for nothing does nothing
                $control = $this->getRoot()->findById($this->labelTargets[$widget]);

                return null !== $control && $control !== $widget && $this->activate($control);
            }
            // Text with links, or an image map: only its links do something, the terminal following the others
            if ($widget instanceof LinksInterface) {
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
        $this->tui?->requestRender();
        $id = rawurldecode($fragment);
        if ('' === $id && $texts) {
            $this->target?->setState('target', false);
            $this->target = null;
            $this->tui?->reveal($texts[0][0]);

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
        $this->tui?->reveal($target);
        if ($target instanceof FocusableInterface && !$target->hasState('disabled')) {
            $this->tui?->setFocus($target);
        }

        return true;
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
            $words = static fn (string $text): string => str_replace(' ', '\\s+', preg_quote(trim((string) preg_replace('/\s+/u', ' ', $text)), '/'));
            $target = '(?<![\\p{L}\\p{N}_])'.$words($parts[0]).(isset($parts[1]) ? '.*?'.$words($parts[1]) : '').'(?![\\p{L}\\p{N}_])';
            $patterns[] = '/'.(null !== $prefix ? $words($prefix).'\\s*' : '').'('.$target.')'.(null !== $suffix ? '\\s*'.$words($suffix) : '').'/isu';
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
        if ((!$widget instanceof FocusableInterface && !$widget instanceof ActivatableInterface) || $widget->hasState('disabled')) {
            return false;
        }
        if ($widget instanceof FocusableInterface) {
            $this->tui?->setFocus($widget);
        }

        return !$widget instanceof ActivatableInterface || $widget->activate() || $widget instanceof FocusableInterface;
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
    }

    private function overlayAt(AbstractWidget $root, int $row, int $col): ?AbstractWidget
    {
        $overlays = [];
        $style = static fn (AbstractWidget $widget): Style => $widget->getContext()?->resolveStyle($widget) ?? new Style();
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
            $style = $context->resolveStyle($current);
            if ($style->getHidden() || $style->getInvisible() || ($current !== $widget && $style->getContentHidden())) {
                return false;
            }
        }

        return $this->tui?->getWidgetRect($widget)?->contains($row, $col) ?? false;
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

    private function flowAt(AbstractWidget $widget, int $row, int $col): ?AbstractWidget
    {
        if (!$this->contains($widget, $row, $col)) {
            return null;
        }
        $children = $widget instanceof ParentInterface ? $widget->all() : [];
        if (null !== $widget->getContext()?->resolveStyle($widget)->getDirection()) {
            usort($children, static fn (AbstractWidget $a, AbstractWidget $b): int => ($a->getContext()?->resolveStyle($a)->getOrder() ?? 0) <=> ($b->getContext()?->resolveStyle($b)->getOrder() ?? 0));
        }
        foreach (array_reverse($children) as $child) {
            $style = $child->getContext()?->resolveStyle($child);
            if ($style?->getPosition()?->isOutOfFlow() || ($style?->getPosition()?->zIndex ?? 0) < 0) {
                continue;
            }
            if (null !== $hit = $this->flowAt($child, $row, $col)) {
                return $hit;
            }
        }

        return $widget;
    }
}
