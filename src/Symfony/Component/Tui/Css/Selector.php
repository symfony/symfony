<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Symfony\Component\Tui\Compatibility\Support;
use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * A complex CSS selector, prepared for the native DOM selector engine.
 *
 * PHP's DOM matches selectors natively (Selectors Level 4, including :is(),
 * :where(), :has() and :not() lists) but has no notion of user interaction.
 * Every other pseudo-class, like :focus or :hover, is a widget state: it is
 * rewritten to an attribute selector on the state attribute the mirror keeps
 * in sync, which has the same specificity.
 *
 * A trailing pseudo-element (select::selected, .list::selected:focus) names
 * a widget sub-element; the pseudo-classes after it apply to the widget.
 *
 * @experimental
 *
 * @internal
 */
final class Selector
{
    public const STATE_ATTRIBUTE = 'data-tui-state';
    /** The language of the element: its lang attribute, or that of its closest ancestor setting one */
    public const LANG_ATTRIBUTE = 'data-tui-lang';
    /** "rtl" when the dir attribute of the element, or that of its closest ancestor setting one, is rtl */
    public const DIR_ATTRIBUTE = 'data-tui-dir';

    /** The attribute of the content of a <details>, which ::details-content selects */
    public const DETAILS_CONTENT_ATTRIBUTE = 'data-tui-details-content';

    /** Pseudo-classes the native engine matches from the document structure */
    private const STRUCTURAL = [
        'root', 'empty',
        'first-child', 'last-child', 'only-child',
        'first-of-type', 'last-of-type', 'only-of-type',
        'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type',
    ];

    /** Functional pseudo-classes whose argument is a selector list */
    private const LOGICAL = ['not', 'is', 'where', 'has', 'matches', 'any', '-webkit-any', '-moz-any'];

    /** Functional pseudo-elements of what a terminal does not draw: they select nothing */
    private const IGNORED_FUNCTIONAL_PSEUDO_ELEMENTS = ['cue', 'slotted', 'part', 'view-transition-group', 'view-transition-image-pair', 'view-transition-old', 'view-transition-new'];

    /** Functional pseudo-classes of what a terminal does not have: they match nothing */
    private const IGNORED_FUNCTIONAL_PSEUDO_CLASSES = ['host', 'host-context', 'active-view-transition-type'];

    /** Pseudo-classes that are spelled differently in widget states */
    private const STATE_ALIASES = [
        'focus-visible' => 'focus',
    ];

    private function __construct(
        public readonly string $source,
        public readonly string $native,
        public readonly int $specificity,
        public readonly ?string $pseudoElement,
        public readonly bool $dynamic,
    ) {
    }

    /**
     * Splits a selector list into its complex selectors.
     *
     * @return list<self>
     *
     * @throws InvalidArgumentException when the selector is not valid
     */
    public static function parseList(string $selectorList): array
    {
        $selectors = [];
        foreach (self::splitTopLevel($selectorList) as $selector) {
            $selectors[] = self::parse($selector);
        }

        return $selectors;
    }

    /** The attributes of the composed shadow trees: their elements, their hosts and the light elements slotted in them */
    public const TREE_ATTRIBUTE = 'data-tui-tree';
    public const HOST_ATTRIBUTE = 'data-tui-host';
    public const SLOTTED_ATTRIBUTE = 'data-tui-slotted';
    /** The part names an element of a shadow tree has as the tree of the number after it sees them, 0 for the document */
    public const PART_ATTRIBUTE = 'data-tui-part-';

    /**
     * A selector of a style sheet of a shadow tree, or of the document when it has some, as it matches in the
     * composed tree: :host is the host, ::slotted() the light elements in its slots, ::part() the elements of a shadow
     * tree with that part, and the subject is in the tree of the style sheet (0 for the document).
     */
    public static function inTree(string $selector, int $tree): string
    {
        $host = '['.self::HOST_ATTRIBUTE.'="'.$tree.'"]';
        // What a compound of the ancestors of :host-context() is, which no tree condition is added to
        $outside = ':not([data-tui-context])';
        if (0 !== $tree) {
            while (false !== $at = stripos($selector, ':host-context(')) {
                $end = self::findClosing($selector, $at + 13, '(', ')');
                $selector = substr($selector, 0, $at).':is('.substr($selector, $at + 14, $end - $at - 14).')'.$outside.' '.$host.substr($selector, $end + 1);
            }
            while (false !== $at = stripos($selector, ':host(')) {
                $end = self::findClosing($selector, $at + 5, '(', ')');
                $selector = substr($selector, 0, $at).$host.':is('.substr($selector, $at + 6, $end - $at - 6).')'.substr($selector, $end + 1);
            }
            $selector = (string) preg_replace('/:host(?![\w(-])/i', $host, $selector);
        }
        // ::slotted(x): the light elements slotted in the tree, children of a slot of it; after a combinator, of any
        if (false !== $at = stripos($selector, '::slotted(')) {
            $end = self::findClosing($selector, $at + 9, '(', ')');
            $before = substr($selector, 0, $at);
            $trimmed = rtrim($before);
            $slot = '' === $trimmed ? '' : ($trimmed !== $before || \in_array($trimmed[-1], ['>', '+', '~'], true) ? $trimmed.' * > ' : $trimmed.' > ');
            $selector = $slot.'['.self::SLOTTED_ATTRIBUTE.'="'.$tree.'"]:is('.substr($selector, $at + 10, $end - $at - 10).')'.substr($selector, $end + 1);
        }
        // ::part(name): the elements of a shadow tree in what the selector before it matches, with that part as this
        // tree sees it: their own part in a tree whose host is in it, or the part a host exports
        if (false !== $at = stripos($selector, '::part(')) {
            $end = self::findClosing($selector, $at + 6, '(', ')');
            $parts = preg_split('/\s+/', trim(substr($selector, $at + 7, $end - $at - 7)), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
            $before = rtrim(substr($selector, 0, $at));
            $selector = ('' === $before ? '' : $before.' ').implode('', array_map(static fn (string $part) => '['.self::PART_ATTRIBUTE.$tree.'~="'.addcslashes($part, '"\\').'"]', $parts ?: ['*'])).substr($selector, $end + 1);
        }

        // Every compound in the tree, its host counting: those of the document outside of every tree
        $condition = 0 === $tree ? ':not(['.self::TREE_ATTRIBUTE.'])' : ':is('.$host.', ['.self::TREE_ATTRIBUTE.'="'.$tree.'"])';
        $compounds = [];
        $depth = 0;
        $start = null;
        $pseudo = null;
        for ($i = 0, $length = \strlen($selector); $i <= $length; ++$i) {
            $char = $selector[$i] ?? ' ';
            if ($i < $length && ('(' === $char || '[' === $char)) {
                ++$depth;
            } elseif ($i < $length && (')' === $char || ']' === $char)) {
                --$depth;
            } elseif ($i < $length && '\\' === $char) {
                // An escape: up to six hex digits and the whitespace ending them, or a character
                $start ??= $i;
                $hex = strspn($selector, '0123456789abcdefABCDEF', $i + 1, 6);
                $i += $hex > 0 ? $hex + (\in_array($selector[$i + $hex + 1] ?? '', [' ', "\t", "\n"], true) ? 1 : 0) : 1;
                continue;
            } elseif ($i < $length && ('"' === $char || "'" === $char)) {
                $i = false !== ($close = strpos($selector, $char, $i + 1)) ? $close : $length - 1;
            } elseif (0 === $depth && \in_array($char, [' ', '>', '+', '~', "\t", "\n"], true)) {
                if (null !== $start) {
                    $compounds[] = [$start, $pseudo ?? $i];
                }
                [$start, $pseudo] = [null, null];
                continue;
            } elseif (0 === $depth && ':' === $char && ':' === ($selector[$i + 1] ?? '')) {
                $pseudo ??= $i;
            }
            $start ??= $i;
        }
        foreach (array_reverse($compounds) as [$from, $to]) {
            $compound = substr($selector, $from, $to - $from);
            if (str_contains($compound, self::SLOTTED_ATTRIBUTE) || str_contains($compound, self::PART_ATTRIBUTE) || str_contains($compound, $outside)) {
                continue;
            }
            $selector = substr($selector, 0, $to).$condition.substr($selector, $to);
        }

        return $selector;
    }

    /**
     * @throws InvalidArgumentException when the selector is not valid
     */
    public static function parse(string $selector): self
    {
        $selector = trim($selector);
        if ('' === $selector) {
            throw new InvalidArgumentException('A selector cannot be empty.');
        }

        $dynamic = false;
        $pseudoElement = null;
        [$native, $specificity] = self::rewrite($selector, $dynamic, $pseudoElement, true);

        $native = '' === trim($native) ? '*' : trim($native);

        return new self($selector, $native, $specificity, $pseudoElement, $dynamic);
    }

    /**
     * Returns the selector that matches a widget state.
     */
    public static function stateSelector(string $state): string
    {
        return '['.self::STATE_ATTRIBUTE.'~="'.$state.'"]';
    }

    /**
     * @return array<string, Support>
     */
    public static function getSupport(): array
    {
        $support = [
            'css.selectors.type' => Support::supported(),
            'css.selectors.class' => Support::supported(),
            'css.selectors.id' => Support::supported(),
            'css.selectors.attribute' => Support::supported(),
            'css.selectors.universal' => Support::supported(),
            'css.selectors.descendant' => Support::supported(),
            'css.selectors.child' => Support::supported(),
            'css.selectors.next-sibling' => Support::supported(),
            'css.selectors.subsequent-sibling' => Support::supported(),
            'css.selectors.list' => Support::supported(),
            'css.selectors.focus' => Support::supported('Matches the focused widget.'),
            'css.selectors.focus-visible' => Support::supported('Same as :focus: focus always comes from the keyboard.'),
            'css.selectors.focus-within' => Support::supported(),
            'css.selectors.hover' => Support::supported('The widget under the pointer and its ancestors, from HtmlView::hover() or the "hover" state, and the link of a text under it, like a:hover.'),
            'css.selectors.active' => Support::partial('The widget a pointer button is pressed on and its ancestors, from HtmlView::press() until HtmlView::release() or click(), or the "active" state the application sets; Enter or Space on the focused control makes it active for 0.15 s, terminals not reporting key releases.'),
            'css.selectors.disabled' => Support::supported(),
            'css.selectors.enabled' => Support::supported(),
            'css.selectors.checked' => Support::partial('Matches when the widget sets the "checked" state.'),
            'css.selectors.placeholder-shown' => Support::supported('An <input> showing its placeholder.'),
            'css.selectors.required' => Support::supported(),
            'css.selectors.optional' => Support::supported(),
            'css.selectors.read-write' => Support::supported('Text inputs and textareas that are not readonly or disabled.'),
            'css.selectors.read-only' => Support::supported('Every element that is not :read-write, form controls and other elements alike.'),
            'css.selectors.valid' => Support::partial('Constraint validation of <input>, <textarea> and checkboxes: required, minlength, maxlength, pattern (PCRE), and the email, url and number types; a <form> or a <fieldset> is invalid when a control inside is, like HTML.'),
            'css.selectors.invalid' => Support::partial('See :valid.'),
            'css.selectors.user-valid' => Support::supported('Once the value was changed.'),
            'css.selectors.user-invalid' => Support::supported('Once the value was changed.'),
            'css.selectors.in-range' => Support::supported('<input type="number"> with min or max.'),
            'css.selectors.out-of-range' => Support::supported('<input type="number"> with min or max.'),
            'css.selectors.open' => Support::supported('An expanded <details>, an open <dialog>, a <select> showing its list, or an <input list> showing its suggestions.'),
            'css.selectors.modal' => Support::supported('A dialog opened with HtmlView::showModal().'),
            'css.selectors.target' => Support::supported('The element a link to its fragment (<a href="#id">) was clicked to, or HtmlView::navigate() went to; it is scrolled into view, focused when it takes the focus.'),
            'css.selectors.popover-open' => Support::supported('A popover shown, by an invoker button or HtmlView::showPopover().'),
            'css.selectors.backdrop' => Support::partial('Of a modal dialog: its background, translucent or not, is laid over what is under the dialog, the terminal background showing through but for an opaque one; opacity alone dims it; no other property.'),
            'css.selectors.default' => Support::partial('The first submit button of a form, and the checkboxes and radios with the checked attribute, checked or not since, and the options of a customizable <select> with the selected attribute; not the options of a plain <select>, which are not elements.'),
            'css.selectors.indeterminate' => Support::supported('A <progress> without a value, and the radios of a group (a name in a form) of which none is checked; a checkbox when the application sets the "indeterminate" state, like the indeterminate IDL attribute.'),
            'css.selectors.condensed' => Support::supported('Tui extension: the effective density, from data-density or the media context.'),
            'css.selectors.state' => Support::supported('Tui extension: any other pseudo-class matches the widget state of that name, see AbstractWidget::setState().'),
            'css.selectors.heading' => Support::supported('The h1 to h6 elements, or those of the levels listed, like :heading(2, 3).'),
            'css.selectors.lang' => Support::supported('The lang attribute of the element or of its closest ancestor setting one, by language range, * matching any language; lang="" is no language.'),
            'css.selectors.dir' => Support::supported('The dir attribute of the element or of its closest ancestor setting ltr, rtl or auto (the direction of its first strong character).'),
            'css.selectors.scope' => Support::supported('Same as :root: style sheets are not scoped.'),
            'css.selectors.defined' => Support::supported('Matches every element: there are no undefined custom elements.'),
            'css.selectors.first-line' => Support::partial('On text blocks: the first line as wrapped, under the styles of the inline elements on it; colors and text attributes only; written :first-line too, like CSS 2.'),
            'css.selectors.first-letter' => Support::partial('On text blocks: the first letter with the punctuation before it; colors and text attributes only; written :first-letter too, like CSS 2.'),
            'css.selectors.any-link' => Support::supported('Matches <a href>, styled inline.'),
            'css.selectors.link' => Support::supported('A link not visited, see :visited.'),
            'css.selectors.details-content' => Support::supported('The content of a <details>, styled like a box while it is open; the pseudo-classes after it apply to the content.'),
            'css.selectors.selection' => Support::ignored('The terminal selects text itself, in its own colors.'),
            'css.selectors.target-text' => Support::partial('The text a text fragment goes to, like #:~:text=start,end or #id:~:text=prefix-,text,-suffix, from HtmlView::navigate() or a link: the first match of each text directive in a text block, whole words, the case not mattering, scrolled into view; in the colors and text attributes of its rules, those of a mark by default; not across text blocks.'),
            'css.selectors.visited' => Support::supported('A link followed, by its href, or one the application says it visited (HtmlView::setVisitedLinks()), styled in any property, a terminal having no history to keep from the page.'),
            'css.selectors.fullscreen' => Support::ignored('The view fills the terminal already.'),
            'css.selectors.playing' => Support::supported('Never: audio and video are not played.'),
            'css.selectors.paused' => Support::supported('Every <video> and <audio>: they are not played, so always paused.'),
            'css.selectors.placeholder' => Support::partial('The hint of an empty <input> or <textarea>: its colors and text attributes, dimmed by the user agent stylesheet (opacity: 0.5), a transparent color hiding it, like the fields of the floating labels of Bootstrap; the pseudo-classes after it apply to the field.'),
            'css.selectors.autofill' => Support::ignored('Fields are never filled in by the terminal.'),
            'css.selectors.file-selector-button' => Support::ignored('A file input is a path typed in, without a button.'),
            'css.selectors.spelling-error' => Support::ignored('Text is not checked for spelling.'),
            'css.selectors.grammar-error' => Support::ignored('Text is not checked for grammar.'),
            'css.selectors.highlight' => Support::partial('The texts registered with HtmlView::highlight(name, texts), found in each text block across its inline elements: drawn in the colors and text attributes of the ::highlight(name) rules of the block over those of the text, a highlight registered later over the others; ranges are texts, scripts not running.'),
            'css.selectors.cue' => Support::ignored('Videos and their captions are not played.'),
            'css.selectors.past' => Support::ignored('Videos and their captions are not played.'),
            'css.selectors.future' => Support::ignored('Videos and their captions are not played.'),
            'css.selectors.picture-in-picture' => Support::ignored('Videos are not played.'),
            'css.selectors.host' => Support::supported('In the style sheets of a declarative shadow root: its host, the rules of the page winning over them whatever their specificity, but for important declarations, like the context of the cascade.'),
            'css.selectors.hostfunction' => Support::supported('The host when it matches the selector; :host-context() when an ancestor does, not the host itself.'),
            'css.selectors.slotted' => Support::supported('In the style sheets of a declarative shadow root: the elements of the page slotted in its slots, after a slot or not.'),
            'css.selectors.part' => Support::supported('The elements of the shadow trees in what the selector before it matches with the parts named: the parts of a tree whose host is in the tree of the rule, and those their hosts export with exportparts.'),
            'css.selectors.first' => Support::ignored('Nothing is printed: @page is ignored.'),
            'css.selectors.left' => Support::ignored('Nothing is printed: @page is ignored.'),
            'css.selectors.right' => Support::ignored('Nothing is printed: @page is ignored.'),
            'css.selectors.namespace' => Support::partial('*|type and *|* match like type and *; elements are in the HTML namespace and @namespace is ignored, so with any other prefix, or none (|type), a selector matches nothing.'),
            'css.selectors.active-view-transition' => Support::ignored('Changes are drawn at once, without view transitions; see transition.'),
            'css.selectors.active-view-transition-type' => Support::ignored('Changes are drawn at once, without view transitions; see transition.'),
            'css.selectors.view-transition' => Support::ignored('Changes are drawn at once, without view transitions; see transition.'),
            'css.selectors.view-transition-group' => Support::ignored('Changes are drawn at once, without view transitions; see transition.'),
            'css.selectors.view-transition-image-pair' => Support::ignored('Changes are drawn at once, without view transitions; see transition.'),
            'css.selectors.view-transition-old' => Support::ignored('Changes are drawn at once, without view transitions; see transition.'),
            'css.selectors.view-transition-new' => Support::ignored('Changes are drawn at once, without view transitions; see transition.'),
            'css.selectors.picker' => Support::supported('::picker(select): the list of a <select> drawn as a dropdown, styled like a box: border, padding, background, colors and the rest, as wide as its widest option with them; the pseudo-classes after it apply to the list.'),
            'css.selectors.scroll-marker' => Support::partial('On the children of a scroll container with scroll-marker-group: their marker in the group, its content, colors and text attributes; :target-current after it matches the one of the child scrolled to.'),
            'css.selectors.scroll-marker-group' => Support::partial('The row of the markers: its colors and text attributes.'),
            'css.selectors.target-current' => Support::supported('The child of a scroll container whose ::scroll-marker is the current one, the last one that starts at where it is scrolled to or before (the last one shown at the end), like li::scroll-marker:target-current.'),
            'css.selectors.scroll-button' => Support::partial('::scroll-button(up), (down), (left), (right), their logical names and (*): the buttons of a scroll container with content, on a row inside it, the up one above the content, the others below, the left one at the start and the right one at the end; a click scrolls a page (85% of the viewport) that way, the button dim when it cannot; their colors and text attributes; they do not take the focus.'),
            'css.selectors.picker-icon' => Support::supported('The ▾ of a <select> drawn as a dropdown: its colors and text attributes, and its content, none hiding it.'),
            'css.selectors.checkmark' => Support::partial('The → of the selected option of a <select>, in a dropdown or a list: its colors and text attributes, and its content, none hiding it; not the boxes of a multiple one.'),
            'css.selectors.pseudo-element' => Support::partial('Names a widget sub-element, like select::selected; the pseudo-classes after it apply to the widget.'),
        ];

        foreach ([...self::STRUCTURAL, ...self::LOGICAL] as $pseudoClass) {
            if (!\in_array($pseudoClass, ['matches', 'any', '-webkit-any', '-moz-any'], true)) {
                $support['css.selectors.'.$pseudoClass] = Support::supported();
            }
        }

        return $support;
    }

    /**
     * @return array{string, int}
     */
    private static function rewrite(string $selector, bool &$dynamic, ?string &$pseudoElement, bool $topLevel): array
    {
        $out = '';
        $ids = $classes = $types = 0;
        $length = \strlen($selector);
        $i = 0;
        $compoundStart = true;

        $universalAt = null;
        $picker = false;
        while ($i < $length) {
            self::insertUniversal($out, $universalAt);
            $char = $selector[$i];

            if ((null !== $pseudoElement || $picker) && !\in_array($char, [':', ' ', "\t", "\n"], true)) {
                throw new InvalidArgumentException(\sprintf('Only pseudo-classes can follow the pseudo-element in "%s".', $selector));
            }

            if (' ' === $char || "\t" === $char || "\n" === $char || '>' === $char || '+' === $char || '~' === $char) {
                $out .= $char;
                $compoundStart = true;
                ++$i;
                continue;
            }

            if ('"' === $char || "'" === $char) {
                $end = self::skipString($selector, $i);
                $out .= substr($selector, $i, $end - $i);
                $i = $end;
                continue;
            }

            if ('[' === $char) {
                $end = self::findClosing($selector, $i, '[', ']');
                $out .= substr($selector, $i, $end - $i + 1);
                ++$classes;
                $i = $end + 1;
                $compoundStart = false;
                continue;
            }

            if ('#' === $char || '.' === $char) {
                $name = self::readIdentifier($selector, $i + 1);
                if ('' === $name) {
                    throw new InvalidArgumentException(\sprintf('Expected a name after "%s" in "%s".', $char, $selector));
                }
                '#' === $char ? ++$ids : ++$classes;
                $out .= $char.$name;
                $i += 1 + \strlen($name);
                $compoundStart = false;
                continue;
            }

            if (':' === $char) {
                if (':' === ($selector[$i + 1] ?? '')) {
                    if (!$topLevel) {
                        throw new InvalidArgumentException(\sprintf('A pseudo-element cannot be nested in "%s".', $selector));
                    }
                    $name = self::readIdentifier($selector, $i + 2);
                    if ('' === $name || null !== $pseudoElement || $picker) {
                        throw new InvalidArgumentException(\sprintf('Invalid pseudo-element in "%s".', $selector));
                    }
                    $i += 2 + \strlen($name);
                    ++$types;
                    // ::details-content, the content of a <details>: a widget of its own
                    if ('details-content' === strtolower($name)) {
                        $out .= ' > ['.self::DETAILS_CONTENT_ATTRIBUTE.']';
                        $picker = true;
                        continue;
                    }
                    // ::scroll-button(up), a button of a scroll container: its direction, physical, or * for all of them
                    if ('(' === ($selector[$i] ?? '') && 'scroll-button' === strtolower($name)) {
                        $end = self::findClosing($selector, $i, '(', ')');
                        $direction = match ($argument = strtolower(trim(substr($selector, $i + 1, $end - $i - 1)))) {
                            'up', 'block-start' => 'up',
                            'down', 'block-end' => 'down',
                            'left', 'inline-start' => 'left',
                            'right', 'inline-end' => 'right',
                            '*' => '*',
                            default => throw new InvalidArgumentException(\sprintf('Invalid direction "%s" of "::scroll-button()" in "%s".', $argument, $selector)),
                        };
                        $pseudoElement = 'scroll-button('.$direction.')';
                        $i = $end + 1;
                        continue;
                    }
                    // ::picker(select), the list of a select drawn as a dropdown: a widget of its own, the select mirrored inside
                    if ('(' === ($selector[$i] ?? '')) {
                        $end = self::findClosing($selector, $i, '(', ')');
                        // ::highlight(name), the text of a custom highlight registered with HtmlView::highlight()
                        if ('highlight' === strtolower($name)) {
                            $argument = trim(substr($selector, $i + 1, $end - $i - 1));
                            if (!preg_match('/^-?[a-zA-Z_][\w-]*$/', $argument)) {
                                throw new InvalidArgumentException(\sprintf('Invalid name "%s" of "::highlight()" in "%s".', $argument, $selector));
                            }
                            $pseudoElement = 'highlight('.$argument.')';
                            $i = $end + 1;
                            continue;
                        }
                        // ::part(name)...: no widget has them
                        if (\in_array(strtolower($name), self::IGNORED_FUNCTIONAL_PSEUDO_ELEMENTS, true)) {
                            $pseudoElement = strtolower($name);
                            $i = $end + 1;
                            continue;
                        }
                        if ('picker' !== strtolower($name) || 'select' !== strtolower(trim(substr($selector, $i + 1, $end - $i - 1)))) {
                            throw new InvalidArgumentException(\sprintf('The "::%s()" pseudo-element is not supported in "%s".', $name, $selector));
                        }
                        $out .= ' > select';
                        $picker = true;
                        $i = $end + 1;
                        continue;
                    }
                    $pseudoElement = strtolower($name);
                    continue;
                }

                $name = strtolower(self::readIdentifier($selector, $i + 1));
                if ('' === $name) {
                    throw new InvalidArgumentException(\sprintf('Expected a name after ":" in "%s".', $selector));
                }
                // The pseudo-elements of CSS 2 keep their single colon, like the td:before of EasyAdmin
                if (\in_array($name, ['before', 'after', 'first-line', 'first-letter'], true)) {
                    if (!$topLevel) {
                        throw new InvalidArgumentException(\sprintf('A pseudo-element cannot be nested in "%s".', $selector));
                    }
                    if (null !== $pseudoElement || $picker) {
                        throw new InvalidArgumentException(\sprintf('Invalid pseudo-element in "%s".', $selector));
                    }
                    $i += 1 + \strlen($name);
                    ++$types;
                    $pseudoElement = $name;
                    continue;
                }
                $i += 1 + \strlen($name);
                $argument = null;
                if ('(' === ($selector[$i] ?? '')) {
                    $end = self::findClosing($selector, $i, '(', ')');
                    $argument = substr($selector, $i + 1, $end - $i - 1);
                    $i = $end + 1;
                }
                // Where the compound starts, in case what the pseudo-class becomes needs a universal selector before it
                $universalAt = $compoundStart ? \strlen($out) : null;
                $compoundStart = false;

                if (\in_array($name, self::LOGICAL, true)) {
                    if (null === $argument) {
                        throw new InvalidArgumentException(\sprintf('":%s" expects a selector list in "%s".', $name, $selector));
                    }
                    $parts = [];
                    $max = 0;
                    foreach (self::splitTopLevel($argument) as $part) {
                        // :has() takes relative selectors (:has(> p)): rewrite their compound part only
                        $prefix = '';
                        if ('has' === $name && preg_match('/^\s*([>+~])/', $part, $m)) {
                            $prefix = $m[1].' ';
                            $part = substr(ltrim($part), 1);
                        }
                        $ignored = null;
                        [$rewritten, $partSpecificity] = self::rewrite(trim($part), $dynamic, $ignored, false);
                        $parts[] = $prefix.trim($rewritten);
                        $max = max($max, $partSpecificity);
                    }
                    $native = \in_array($name, ['matches', 'any', '-webkit-any', '-moz-any'], true) ? 'is' : $name;
                    $out .= ':'.$native.'('.implode(', ', $parts).')';
                    if ('where' !== $name) {
                        $ids += intdiv($max, 10000);
                        $classes += intdiv($max % 10000, 100);
                        $types += $max % 100;
                    }
                    continue;
                }

                if (\in_array($name, self::STRUCTURAL, true)) {
                    $out .= ':'.$name.(null !== $argument ? '('.$argument.')' : '');
                    ++$classes;
                    if (null !== $argument && preg_match('/\sof\s+(.+)$/s', $argument, $m)) {
                        $ignored = null;
                        $max = self::rewrite($m[1], $dynamic, $ignored, false)[1];
                        $ids += intdiv($max, 10000);
                        $classes += intdiv($max % 10000, 100);
                        $types += $max % 100;
                    }
                    continue;
                }

                // Pseudo-classes the native engine lacks, rewritten to attribute selectors of the same weight
                if (null !== $rewritten = self::rewriteUnsupported($name, $argument, $selector)) {
                    $out .= $rewritten;
                    ++$classes;
                    continue;
                }

                // :host(.a), :active-view-transition-type(a)...: a state no widget has
                if (\in_array($name, self::IGNORED_FUNCTIONAL_PSEUDO_CLASSES, true)) {
                    $argument = null;
                }

                if (null !== $argument) {
                    throw new InvalidArgumentException(\sprintf('The ":%s()" pseudo-class is not supported in "%s".', $name, $selector));
                }

                $dynamic = true;
                ++$classes;
                $out .= match ($name) {
                    'enabled' => ':not('.self::stateSelector('disabled').')',
                    // A link not visited
                    'link' => ':is(a[href], area[href]):not('.self::stateSelector('visited').')',
                    'visited' => ':is(a[href], area[href])'.self::stateSelector('visited'),
                    // Every element that is not editable, not only form controls
                    'read-only' => ':not('.self::stateSelector('read-write').')',
                    default => self::stateSelector(self::STATE_ALIASES[$name] ?? $name),
                };
                continue;
            }

            // A namespace prefix: *| is any namespace; @namespace is ignored, so no other prefix is declared and the
            // elements, all in the HTML namespace, match none
            $noNamespace = false;
            if ($compoundStart && preg_match('/\G(\*|-?[a-zA-Z_][\w-]*)?\|(?!=)/', $selector, $m, 0, $i)) {
                $noNamespace = '*' !== ($m[1] ?? '');
                $i += \strlen($m[0]);
                $char = $selector[$i] ?? '';
            }

            if ('*' === $char) {
                $out .= '*'.($noNamespace ? ':not(*)' : '');
                ++$i;
                $compoundStart = false;
                continue;
            }

            if ($compoundStart) {
                $name = self::readIdentifier($selector, $i);
                if ('' !== $name) {
                    ++$types;
                    // As written: the native engine matches HTML names in any case, and SVG ones, like feFlood, in theirs
                    $out .= $name.($noNamespace ? ':not(*)' : '');
                    $i += \strlen($name);
                    $compoundStart = false;
                    continue;
                }
            }

            throw new InvalidArgumentException(\sprintf('Unexpected "%s" in selector "%s".', $char, $selector));
        }

        self::insertUniversal($out, $universalAt);

        return [$out, $ids * 10000 + min($classes, 99) * 100 + min($types, 99)];
    }

    /**
     * PHP's engine only tests the nearest ancestor for a compound that starts with a pseudo-class taking a selector,
     * like ":is(.a) p" or ":not(.b) p": an explicit universal selector, "*:is(.a) p", makes it test them all.
     */
    private static function insertUniversal(string &$out, ?int &$position): void
    {
        if (null !== $position && preg_match('/\G:(?:is|where|not|has|nth-[a-z-]+)\(/', $out, $m, 0, $position)) {
            $out = substr($out, 0, $position).'*'.substr($out, $position);
        }
        $position = null;
    }

    private static function rewriteUnsupported(string $name, ?string $argument, string $selector): ?string
    {
        switch ($name) {
            case 'scope':
                return null === $argument ? ':root' : null;

            case 'paused':
                // Audio and video are never played: they are always paused
                return null === $argument ? ':is(video, audio)' : null;

            case 'defined':
                // Every element is defined: there are no custom elements waiting for a definition
                return null === $argument ? ':is(*)' : null;

            case 'any-link':
                return null === $argument ? ':is(a[href], area[href])' : null;

            case 'heading':
                if (null === $argument) {
                    return ':is(h1, h2, h3, h4, h5, h6)';
                }
                $levels = [];
                foreach (self::splitTopLevel($argument) as $level) {
                    $level = trim($level);
                    if (!preg_match('/^\+?0*[1-9]\d*$/', $level)) {
                        throw new InvalidArgumentException(\sprintf('":heading()" expects heading levels in "%s".', $selector));
                    }
                    // A level past 6 matches no element, like a browser
                    $levels[] = (int) $level <= 6 ? 'h'.(int) $level : 'h7';
                }
                if (!$levels) {
                    throw new InvalidArgumentException(\sprintf('":heading()" expects heading levels in "%s".', $selector));
                }

                return ':is('.implode(', ', array_unique($levels)).')';

            case 'lang':
                $ranges = [];
                foreach (self::splitTopLevel((string) $argument) as $range) {
                    $range = trim($range, " \t\n\"'");
                    if (!preg_match('/^[a-z0-9*-]+$/i', $range)) {
                        throw new InvalidArgumentException(\sprintf('Invalid language range "%s" in "%s".', $range, $selector));
                    }
                    $ranges[] = '*' === $range ? '['.self::LANG_ATTRIBUTE.']' : '['.self::LANG_ATTRIBUTE.'|="'.$range.'" i]';
                }
                if (!$ranges) {
                    throw new InvalidArgumentException(\sprintf('":lang()" expects a language in "%s".', $selector));
                }

                return ':is('.implode(', ', $ranges).')';

            case 'dir':
                $direction = strtolower(trim((string) $argument));
                $rtl = '['.self::DIR_ATTRIBUTE.'="rtl"]';

                return match ($direction) {
                    'rtl' => $rtl,
                    'ltr' => ':not('.$rtl.')',
                    default => throw new InvalidArgumentException(\sprintf('":dir()" expects "ltr" or "rtl" in "%s".', $selector)),
                };
        }

        return null;
    }

    /**
     * Splits a comma-separated list, ignoring commas inside brackets, parentheses and strings.
     *
     * @return list<string>
     */
    public static function splitTopLevel(string $list, string $separator = ','): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $length = \strlen($list);
        // Straight to the next character that counts, the others copied at once
        $mask = "\"'()[]\\".$separator;

        for ($i = 0; $i < $length; ++$i) {
            $skip = strcspn($list, $mask, $i);
            if ($skip > 0) {
                $current .= substr($list, $i, $skip);
                $i += $skip;
                if ($i >= $length) {
                    break;
                }
            }
            $char = $list[$i];
            // An escaped character is itself, like the quotes and brackets of the classes of Tailwind, .content-\[\'\'\]
            if ('\\' === $char) {
                $current .= substr($list, $i, 2);
                ++$i;
                continue;
            }
            if ('"' === $char || "'" === $char) {
                $end = self::skipString($list, $i);
                $current .= substr($list, $i, $end - $i);
                $i = $end - 1;
                continue;
            }
            if ('(' === $char || '[' === $char) {
                ++$depth;
            } elseif (')' === $char || ']' === $char) {
                --$depth;
            } elseif ($separator === $char && 0 === $depth) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }

        if ('' !== trim($current)) {
            $parts[] = trim($current);
        }

        return $parts;
    }

    private static function readIdentifier(string $selector, int $offset): string
    {
        // An escape is up to six hex digits and the whitespace ending them, like the \2c of a comma in a class of
        // Tailwind, or a character
        return preg_match('/\G-?(?:[_a-zA-Z\x80-\xff]|\\\\(?:[0-9a-fA-F]{1,6}[ \t\n]?|.))(?:[-_a-zA-Z0-9\x80-\xff]|\\\\(?:[0-9a-fA-F]{1,6}[ \t\n]?|.))*/', $selector, $m, 0, $offset) ? $m[0] : '';
    }

    private static function skipString(string $value, int $offset): int
    {
        $quote = $value[$offset];
        $length = \strlen($value);
        for ($i = $offset + 1; $i < $length; ++$i) {
            if ('\\' === $value[$i]) {
                ++$i;
            } elseif ($quote === $value[$i]) {
                return $i + 1;
            }
        }

        // Closed by the end of the input, like CSS
        return $length;
    }

    private static function findClosing(string $value, int $offset, string $open, string $close): int
    {
        $depth = 0;
        $length = \strlen($value);
        for ($i = $offset; $i < $length; ++$i) {
            $char = $value[$i];
            if ('"' === $char || "'" === $char) {
                $i = self::skipString($value, $i) - 1;
            } elseif ($open === $char) {
                ++$depth;
            } elseif ($close === $char && 0 === --$depth) {
                return $i;
            }
        }

        throw new InvalidArgumentException(\sprintf('Missing "%s" in "%s".', $close, $value));
    }
}
