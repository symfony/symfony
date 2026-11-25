<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

use Symfony\Component\HtmlRenderer\Compatibility\Support;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/**
 * A complex CSS selector, prepared for the native DOM selector engine.
 *
 * PHP's DOM matches selectors natively (Selectors Level 4, including :is(),
 * :where(), :has() and :not() lists) but has no notion of user interaction.
 * Every other pseudo-class, like :focus or :hover, is document state: it is
 * rewritten to an attribute selector on the state attribute the mirror keeps
 * in sync, which has the same specificity.
 *
 * A trailing pseudo-element (select::selected, .list::selected:focus) names
 * a generated box role; the pseudo-classes after it apply to its originating element.
 *
 * @experimental
 *
 * @internal
 */
final class Selector
{
    public const STATE_ATTRIBUTE = 'data-html-renderer-state';
    public const CONTEXT_ATTRIBUTE = 'data-html-renderer-context';
    public const HTML_ATTRIBUTE = 'data-html-renderer-html';
    /** The language of the element: its lang attribute, or that of its closest ancestor setting one */
    public const LANG_ATTRIBUTE = 'data-html-renderer-lang';
    /** "rtl" when the dir attribute of the element, or that of its closest ancestor setting one, is rtl */
    public const DIR_ATTRIBUTE = 'data-html-renderer-dir';
    /** The attribute of the content of a <details>, which ::details-content selects */
    public const DETAILS_CONTENT_ATTRIBUTE = 'data-html-renderer-details-content';
    /** Pseudo-classes the native engine matches from the document structure */
    private const STRUCTURAL = ['root', 'empty', 'first-child', 'last-child', 'only-child', 'first-of-type', 'last-of-type', 'only-of-type', 'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type'];
    /** Functional pseudo-classes whose argument is a selector list */
    private const LOGICAL = ['not', 'is', 'where', 'has', 'matches', 'any', '-webkit-any', '-moz-any'];
    /** Functional pseudo-elements of unsupported generated content: they select nothing */
    private const IGNORED_FUNCTIONAL_PSEUDO_ELEMENTS = ['cue', 'slotted', 'part', 'view-transition-group', 'view-transition-image-pair', 'view-transition-old', 'view-transition-new'];
    /** Functional pseudo-classes of unsupported document contexts: they match nothing */
    private const IGNORED_FUNCTIONAL_PSEUDO_CLASSES = ['host', 'host-context', 'active-view-transition-type'];
    /** Pseudo-classes that are spelled differently in document states */
    private const STATE_ALIASES = ['focus-visible' => 'focus'];
    private ?string $htmlNative = null;
    private ?string $mixedNative = null;
    private ?string $svgNative = null;

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
    public const TREE_ATTRIBUTE = 'data-html-renderer-tree';
    public const HOST_ATTRIBUTE = 'data-html-renderer-host';
    public const SLOTTED_ATTRIBUTE = 'data-html-renderer-slotted';
    /** The part names an element of a shadow tree has as the tree of the number after it sees them, 0 for the document */
    public const PART_ATTRIBUTE = 'data-html-renderer-part-';

    /**
     * A selector of a style sheet of a shadow tree, or of the document when it has some, as it matches in the
     * composed tree: :host is the host, ::slotted() the light elements in its slots, ::part() the elements of a shadow
     * tree with that part, and the subject is in the tree of the style sheet (0 for the document).
     */
    public static function inTree(string $selector, int $tree): string
    {
        $host = '['.self::HOST_ATTRIBUTE.'="'.$tree.'"]';
        // What a compound of the ancestors of :host-context() is, which no tree condition is added to
        $outside = ':not(['.self::CONTEXT_ATTRIBUTE.'])';
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
        $native = self::escapeNonAscii('' === trim($native) ? '*' : trim($native));

        return new self($selector, $native, $specificity, $pseudoElement, $dynamic);
    }

    /**
     * Returns the selector that matches document state.
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
        $support = [];
        foreach (['type', 'class', 'id', 'attribute', 'universal', 'descendant', 'child', 'next-sibling', 'subsequent-sibling', 'list', ...self::STRUCTURAL, 'not', 'is', 'where', 'has', 'heading', 'lang', 'any-link', 'link'] as $name) {
            $support['css.selectors.'.$name] = Support::supported();
        }
        foreach (['focus', 'focus-within', 'hover', 'active', 'checked', 'disabled', 'enabled', 'required', 'optional', 'read-write', 'read-only', 'placeholder-shown', 'valid', 'invalid', 'in-range', 'out-of-range', 'open'] as $name) {
            $support['css.selectors.'.$name] = Support::partial('Matches document state supplied or derived by the rendering session; this is not a complete browser state model.');
        }
        $support['css.selectors.focus-visible'] = Support::partial('Currently aliases :focus; input-modality heuristics are not implemented.');
        $support['css.selectors.state'] = Support::partial('Other pseudo-classes match explicitly supplied document state tokens.');
        $support['css.selectors.dir'] = Support::partial('Matches reflected HTML direction; automatic direction requires the optional intl extension.');
        $support['css.selectors.scope'] = Support::partial('Matches the document root for unscoped stylesheets.');
        foreach (['before', 'after', 'marker', 'selection', 'backdrop', 'picker', 'first-letter'] as $name) {
            $support['css.selectors.'.$name] = Support::partial('Computed pseudo-element styles are consumed by the box builder or text painter.');
        }
        $support['css.selectors.highlight'] = Support::partial('Named string-search highlights are resolved within text blocks before text layout.');
        $support['css.selectors.first-line'] = Support::partial('Applies color and text decoration to the first formatted line; font and other layout-affecting declarations are not applied.');
        foreach (['details-content', 'picker-icon', 'checkmark', 'scroll-marker', 'scroll-marker-group', 'scroll-button', 'target-text', 'part', 'slotted', 'view-transition'] as $name) {
            $support['css.selectors.'.$name] = Support::ignored('Selector parsing does not imply a corresponding generated box or rendering behavior.');
        }

        return $support;
    }

    public static function pseudoElementMatches(?string $rule, string $wanted): bool
    {
        return $rule === $wanted || 'scroll-button(*)' === $rule && str_starts_with($wanted, 'scroll-button(');
    }

    /** Keeps an already compiled selector when evaluating an existing native tree. */
    public function forNativeTree(): self
    {
        $selector = new self($this->forHtml(), $this->native, $this->specificity, $this->pseudoElement, $this->dynamic);
        $selector->htmlNative = $this->forHtml();
        $selector->svgNative = $this->forSvg();
        $selector->mixedNative = $this->forMixedDocument();

        return $selector;
    }

    /** Resolves a wildcard button role while preserving an already compiled native selector. */
    public function forScrollButton(string $direction): self
    {
        if (!\in_array($direction, ['up', 'down', 'left', 'right'], true) || 'scroll-button(*)' !== $this->pseudoElement) {
            throw new InvalidArgumentException('A wildcard scroll button requires a physical direction.');
        }
        $replace = static fn (string $value): string => str_replace('scroll-button-*-disabled', 'scroll-button-'.$direction.'-disabled', $value);
        $selector = new self($replace($this->source), $replace($this->native), $this->specificity, 'scroll-button('.$direction.')', $this->dynamic);
        $selector->htmlNative = $replace($this->forHtml());
        $selector->svgNative = $replace($this->forSvg());
        $selector->mixedNative = $replace($this->forMixedDocument());

        return $selector;
    }

    /** The generated pseudo-element selector used by the document box pipeline. */
    public function forDocument(): self
    {
        $source = $this->source;
        if (null !== $start = ValueTokenizer::findOutsideStrings($source, '/::details-content\\b/i')) {
            $owner = self::parse(substr_replace($source, '::before', $start, \strlen('::details-content')));

            $document = new self($this->source, $owner->native, $this->specificity, 'details-content', $this->dynamic);
            $document->htmlNative = $owner->forHtml();
            $document->svgNative = $owner->forSvg();

            return $document;
        }
        $offset = 0;
        while (null !== $start = ValueTokenizer::findOutsideStrings($source, '/::picker\(\s*select\s*\)/i', $offset)) {
            preg_match('/^::picker\(\s*select\s*\)/i', substr($source, $start), $match);
            $source = substr_replace($source, '::picker', $start, \strlen($match[0]));
            $offset = $start + 8;
        }

        return $source === $this->source ? $this : self::parse($source);
    }

    /** The selector for the HTML mirror, with HTML type names matched without case. */
    public function forHtml(): string
    {
        return $this->htmlNative ??= $this->nativeFor(true);
    }

    /** The selector for SVG, whose type names retain their case. */
    public function forSvg(): string
    {
        return $this->svgNative ??= $this->nativeFor(false);
    }

    /** HTML namespace elements carry HTML_ATTRIBUTE on a mixed document mirror. */
    public function forMixedDocument(): string
    {
        return $this->mixedNative ??= $this->nativeFor(null);
    }

    /** @return \Traversable<int, \Dom\Element> */
    public static function queryAll(\Dom\Document $document, string $selector): \Traversable
    {
        if (!self::legacyEngine() || !str_contains($selector, ':nth-') || null === $type = self::subjectType($selector)) {
            return $document->querySelectorAll($selector);
        }
        // Older engines count the siblings of unrelated elements before testing the subject's type.
        $matches = [];
        foreach ($document->getElementsByTagNameNS('*', $type) as $element) {
            if ($element->matches($selector)) {
                $matches[] = $element;
            }
        }

        return new \ArrayIterator($matches);
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

    private function __construct(public readonly string $source, public readonly string $native, public readonly int $specificity, public readonly ?string $pseudoElement, public readonly bool $dynamic)
    {
    }

    /**
     * How deep selectors nest in selectors, like :is(:is(a)) two levels: each level is read again by each level around
     * it, 1000 of them took seconds; deeper than CSS nesting needs for its :is() of the selectors of parent rules, see
     * {@see CssParser::MAX_NESTING}.
     */
    private const MAX_NESTING = 128;
    private static int $nesting = 0;

    /**
     * @return array{string, int}
     */
    private static function rewrite(string $selector, bool &$dynamic, ?string &$pseudoElement, bool $topLevel, ?bool $html = false, bool $legacy = false): array
    {
        if (self::$nesting > self::MAX_NESTING) {
            throw new InvalidArgumentException(\sprintf('A selector cannot nest more than %d levels of selectors.', self::MAX_NESTING));
        }
        ++self::$nesting;
        try {
            return self::doRewrite($selector, $dynamic, $pseudoElement, $topLevel, $html, $legacy);
        } finally {
            --self::$nesting;
        }
    }

    /**
     * @return array{string, int}
     */
    private static function doRewrite(string $selector, bool &$dynamic, ?string &$pseudoElement, bool $topLevel, ?bool $html, bool $legacy): array
    {
        $out = '';
        $ids = $classes = $types = 0;
        $length = \strlen($selector);
        $i = 0;
        $compoundStart = true;
        $universalAt = null;
        $legacyLogical = false;
        $picker = false;
        while ($i < $length) {
            self::insertUniversal($out, $universalAt, $legacy, $legacyLogical);
            $char = $selector[$i];
            if ((null !== $pseudoElement || $picker) && !\in_array($char, [':', ' ', "\t", "\n"], true)) {
                throw new InvalidArgumentException(\sprintf('Only pseudo-classes can follow the pseudo-element in "%s".', $selector));
            }
            if (' ' === $char || "\t" === $char || "\n" === $char || '>' === $char || '+' === $char || '~' === $char) {
                if ($legacyLogical) {
                    $out .= ':is(*)';
                    $legacyLogical = false;
                }
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
                    // ::details-content uses a dedicated mirrored content element when supplied by a consumer
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
                    // ::picker(select) uses an optional mirrored picker element
                    if ('(' === ($selector[$i] ?? '')) {
                        $end = self::findClosing($selector, $i, '(', ')');
                        // ::highlight(name) selects optional reflected named highlight state
                        if ('highlight' === strtolower($name)) {
                            $argument = trim(substr($selector, $i + 1, $end - $i - 1));
                            if (!preg_match('/^-?[a-zA-Z_][\w-]*$/', $argument)) {
                                throw new InvalidArgumentException(\sprintf('Invalid name "%s" of "::highlight()" in "%s".', $argument, $selector));
                            }
                            $pseudoElement = 'highlight('.$argument.')';
                            $i = $end + 1;
                            continue;
                        }
                        // Unsupported shadow-tree part context
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
                $universalAt = $compoundStart || $legacy ? \strlen($out) : null;
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
                        [$rewritten, $partSpecificity] = self::rewrite(trim($part), $dynamic, $ignored, false, $html, $legacy);
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
                    // An+B, like odd or -n+3, the selector of its of too: a selector of another argument is invalid, the
                    // rule dropped and reported like CSS, not matching nothing silently
                    if (str_starts_with($name, 'nth-') && (null === $argument || !preg_match('/^\s*(?:odd|even|[+-]?\d*n(?:\s*[+-]\s*\d+)?|[+-]?\d+)\s*(?:\sof\s+.+)?$/is', $argument))) {
                        throw new InvalidArgumentException(\sprintf('Invalid argument of ":%s()" in "%s".', $name, $selector));
                    }
                    $out .= ':'.$name.(null !== $argument ? '('.$argument.')' : '');
                    ++$classes;
                    if (null !== $argument && preg_match('/\sof\s+(.+)$/s', $argument, $m)) {
                        $ignored = null;
                        $max = self::rewrite($m[1], $dynamic, $ignored, false, $html, $legacy)[1];
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
                // Unsupported shadow-host and view-transition context
                if (\in_array($name, self::IGNORED_FUNCTIONAL_PSEUDO_CLASSES, true)) {
                    $argument = null;
                }
                if (null !== $argument) {
                    throw new InvalidArgumentException(\sprintf('The ":%s()" pseudo-class is not supported in "%s".', $name, $selector));
                }
                $dynamic = true;
                ++$classes;
                $disabledState = null !== $pseudoElement && str_starts_with($pseudoElement, 'scroll-button(') ? 'scroll-button-'.substr($pseudoElement, 14, -1).'-disabled' : 'disabled';
                $out .= match ($name) {
                    'disabled' => self::stateSelector($disabledState),
                    'enabled' => ':not('.self::stateSelector($disabledState).')',
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
                    $type = $html ? self::htmlType($name) : $name;
                    if (null === $html && ($lower = self::htmlType($name)) !== $name) {
                        $type = ':is('.$name.', '.$lower.':where(['.self::HTML_ATTRIBUTE.']))';
                    }
                    $out .= $type.($noNamespace ? ':not(*)' : '');
                    $i += \strlen($name);
                    $compoundStart = false;
                    continue;
                }
            }
            throw new InvalidArgumentException(\sprintf('Unexpected "%s" in selector "%s".', $char, $selector));
        }
        self::insertUniversal($out, $universalAt, $legacy, $legacyLogical);

        return [$out, $ids * 10000 + min($classes, 99) * 100 + min($types, 99)];
    }

    /**
     * PHP's engine only tests the nearest ancestor for a compound that starts with a pseudo-class taking a selector,
     * like ":is(.a) p" or ":not(.b) p": an explicit universal selector makes it test them all.
     * Older engines need a neutral final pseudo-class instead.
     *
     * @param-out null $position
     */
    private static function insertUniversal(string &$out, ?int &$position, bool $legacy, bool &$legacyLogical): void
    {
        if (null !== $position && preg_match('/\G:(?:is|where|not|has|nth-[a-z-]+)\(/', $out, $m, 0, $position)) {
            if ($legacy) {
                $legacyLogical = true;
            } else {
                $out = substr($out, 0, $position).'*'.substr($out, $position);
            }
        }
        $position = null;
    }

    /**
     * The characters past ASCII of a selector as escapes, outside its strings: the same selector, like CSS says, which
     * the native engine parses, where it rejects a name starting with one, like #été, see
     * https://github.com/php/php-src/issues/25501.
     */
    private static function escapeNonAscii(string $selector): string
    {
        if (!preg_match('/[\x80-\xff]/', $selector)) {
            return $selector;
        }

        return preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\x00-\x7f]/su', static fn (array $m): string => \strlen($m[0]) > 1 && ('"' === $m[0][0] || "'" === $m[0][0]) ? $m[0] : '\\'.dechex(mb_ord($m[0])).' ', $selector) ?? $selector;
    }

    private function nativeFor(?bool $html): string
    {
        if (!self::legacyEngine()) {
            return $this->native;
        }
        $dynamic = false;
        $pseudo = null;

        return self::escapeNonAscii(trim(self::rewrite($this->source, $dynamic, $pseudo, true, $html, true)[0]) ?: '*');
    }

    private static function htmlType(string $name): string
    {
        if (str_contains($name, '\\')) {
            $name = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})[ \t\r\n\f]?/', static function (array $match): string {
                $code = \intval($match[1], 16);

                return $code >= 65 && $code <= 90 ? '\\'.dechex($code + 32).' ' : $match[0];
            }, $name) ?? $name;
        }

        return strtolower($name);
    }

    private static function subjectType(string $selector): ?string
    {
        if (str_contains($selector, '\\') || 1 !== \count(self::splitTopLevel($selector))) {
            return null;
        }
        $depth = $start = 0;
        for ($i = 0, $length = \strlen($selector); $i < $length; ++$i) {
            $char = $selector[$i];
            if ('"' === $char || "'" === $char) {
                $i = self::skipString($selector, $i) - 1;
            } elseif ('(' === $char || '[' === $char) {
                ++$depth;
            } elseif (')' === $char || ']' === $char) {
                --$depth;
            } elseif (0 === $depth && \in_array($char, [' ', "\t", "\n", '>', '+', '~'], true)) {
                $start = $i + 1;
            }
        }

        return preg_match('/\G([a-zA-Z][\w-]*)(?=[.#:\[]|$)/', $selector, $match, 0, $start) ? $match[1] : null;
    }

    private static function legacyEngine(): bool
    {
        static $legacy;

        return $legacy ??= class_exists(\Dom\HTMLDocument::class) && 1 === \count(\Dom\HTMLDocument::createFromString('<!doctype html><div class="a"><section><p></p></section></div>', \LIBXML_NOERROR)->querySelectorAll(':is(.a) p'));
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
