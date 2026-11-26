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

use Psr\Log\LoggerInterface;
use Symfony\Component\HtmlRenderer\Compatibility\Support;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/**
 * Parses CSS style sheets: rules, @media and @import.
 *
 * Like browsers do, it skips what it does not understand (an invalid
 * selector drops its rule, an unknown at-rule its block) and reports it.
 *
 * @experimental
 */
class CssParser
{
    /** At-rules outside the current rendering pipeline, skipped without a report: their notes */
    private const IGNORED_AT_RULES = ['font-face' => '', 'font-feature-values' => '', 'font-palette-values' => '', 'page' => '', 'namespace' => '', 'charset' => '', 'view-transition' => ''];
    /**
     * How deep rules and at-rules nest: each level copies the text of the levels in it, and its selectors are those of
     * its parents too, so 1000 levels took minutes.
     */
    private const MAX_NESTING = 64;
    /**
     * How many @import rules a style sheet and those it imports may follow: each one is parsed again where it is imported,
     * so a few lines importing a large file thousands of times, or files importing each other twice, would take minutes.
     */
    private const MAX_IMPORTS = 256;
    /**
     * How many bytes a style sheet and those it imports may have, each import counted where it is parsed: larger ones
     * are skipped, like the rest of a style sheet past an error.
     */
    public const MAX_BYTES = 8 * 1024 * 1024;
    private int $order = 0;
    private int $nesting = 0;
    /** @var array<string, true> The messages logged already */
    private array $reported = [];
    private int $imports = 0;
    private int $bytes = 0;
    /** @var array<string, string> The style sheets imported while one is parsed, each one loaded once */
    private array $imported = [];
    /** @var array<string, int> The bytes of each style sheet imported, as loaded */
    private array $importedBytes = [];
    /** @var array<string, array{params: list<array{string, ?string, ?string}>, returns: ?string, body: list<array{list<array{string, string}>, string, string}>}> */
    private array $functions = [];
    /** @var list<int> The ranks of the layers in the layer being parsed, from the outermost */
    private array $layerPath = [];

    /**
     * The display of a value in the one-keyword form the layout reads: the two-keyword forms (block flow, inline
     * flex...), list items as blocks, and the -webkit-box of line clamping as a block; the parts of a ruby stay what
     * they are, laid out inline but their annotations over their bases.
     */
    public static function display(string $value): string
    {
        $keywords = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        $legacy = ['-webkit-box' => 'block', '-moz-box' => 'block', '-webkit-inline-box' => 'inline-block', '-moz-inline-box' => 'inline-block', 'list-item' => 'block', 'inline-list-item' => 'inline'];
        if (1 === \count($keywords)) {
            return $legacy[$keywords[0]] ?? trim($value);
        }
        // The outer display, the inner one, and list-item, in any order
        $outer = array_values(array_intersect($keywords, ['block', 'inline', 'run-in']));
        $inner = array_values(array_intersect($keywords, ['flow', 'flow-root', 'table', 'flex', 'grid', 'ruby']));
        $listItem = \in_array('list-item', $keywords, true);
        if (\count($outer) > 1 || \count($inner) > 1 || \count($outer) + \count($inner) + ($listItem ? 1 : 0) !== \count($keywords)) {
            return trim($value);
        }
        $inline = 'inline' === ($outer[0] ?? 'block');

        return match ($inner[0] ?? 'flow') {
            'flow' => $inline ? 'inline' : 'block',
            'flow-root' => $inline ? 'inline-block' : 'flow-root',
            'table' => $inline ? 'inline-table' : 'table',
            'flex' => $inline ? 'inline-flex' : 'flex',
            'grid' => $inline ? 'inline-grid' : 'grid',
            'ruby' => $inline ? 'ruby' : 'block',
            default => throw new \UnhandledMatchError(),
        };
    }
    /**
     * The rank of each layer among its siblings, by the name of its parent, in order of first appearance.
     *
     * @var array<string, array<string, int>>
     */
    private array $layerRanks = [];
    /** The dotted name of the layer being parsed, '' outside any */
    private string $layerName = '';
    private int $anonymousLayers = 0;
    /** @var array<string, true> The layers met since the last takeLayers(), by their dotted name, in order */
    private array $layersMet = [];
    /** @var array<string, string> The dotted name of the layer of each key layerKey() returned */
    private array $layerNames = [];
    /** @var array<string, list<array{float, array<string, string>}>> */
    private array $keyframes = [];
    /** @var array<string, array{bool, ?string, string}> The custom properties registered by @property: whether they inherit, their initial value, their syntax */
    private array $registered = [];
    /** @var array<string, array{system: string, first?: int, symbols?: list<string>, additive?: list<array{int, string}>, negative?: array{string, string}, prefix?: string, suffix?: string, range?: list<array{int|null, int|null}>|null, pad?: array{int, string}|null, fallback?: string, extends?: string, units?: list<string>, formal?: bool}> The styles of the @counter-style rules, by name */
    private array $counterStyles = [];
    /** The root of a @scope without one, like the parent of the <style> it is in */
    private ?string $implicitScope = null;
    /** @var array<string, array<string, string>> The declarations of the @position-try rules, by name */
    private array $positionTries = [];
    /** @var array<string, string> The queries of the @custom-media rules, by name */
    private array $customMedia = [];
    /** @var list<ContainerQuery> The @container conditions of the block being parsed */
    private array $containers = [];

    /**
     * The declaration capabilities are supplied by the rendering integration.
     */
    public function __construct(private readonly ?StylesheetLoaderInterface $loader = null, private readonly ?LoggerInterface $logger = null, private readonly bool $strict = false)
    {
    }

    /**
     * @param list<MediaQuery> $media The media the style sheet applies to, from a media attribute
     *
     * @return list<CssRule>
     */
    public function parse(string $css, array $media = [], ?string $scope = null): array
    {
        if (\strlen($css) > self::MAX_BYTES) {
            $this->report(\sprintf('A style sheet of %d bytes is skipped: a style sheet cannot have more than %d.', \strlen($css), self::MAX_BYTES));

            return [];
        }
        $this->bytes = \strlen($css);
        $this->implicitScope = $scope;
        // Browsers decode away the byte order mark starting a file
        if (str_starts_with($css, '﻿')) {
            $css = substr($css, 3);
        }
        $css = self::stripComments($css);
        $this->collectCustomMedia($css);
        try {
            return $this->parseBlock($css, $media, []);
        } finally {
            $this->implicitScope = null;
            $this->imports = 0;
            $this->bytes = 0;
            $this->imported = [];
            $this->importedBytes = [];
        }
    }

    /**
     * Parses the declarations of a style attribute or a rule block.
     *
     * @param bool $validate Whether to drop the invalid declarations, like a browser in a style sheet
     *
     * @return list<Declaration>
     */
    public function parseDeclarations(string $css, bool $validate = false): array
    {
        return DeclarationParser::parse($css, $this->report(...));
    }

    /**
     * @return array<string, Support>
     */
    public static function getSupport(): array
    {
        return array_fill_keys(['css.at-rules.layer', 'css.at-rules.supports', 'css.selectors.nesting'], Support::supported('Parsed independently of rendering devices.'));
    }

    /**
     * The custom properties registered by @property since the last call, by name.
     *
     * @return array<string, array{bool, ?string, string}> Whether each inherits, its initial value, its syntax
     */
    /**
     * The custom functions of the @function rules parsed since the last call, by name: a later one replaces an earlier
     * one.
     *
     * @return array<string, array{params: list<array{string, ?string, ?string}>, returns: ?string, body: list<array{list<array{string, string}>, string, string}>}>
     */
    public function takeFunctions(): array
    {
        $functions = $this->functions;
        $this->functions = [];

        return $functions;
    }

    public function takeRegisteredProperties(): array
    {
        $registered = $this->registered;
        $this->registered = [];

        return $registered;
    }

    /**
     * The styles of the @counter-style rules parsed since the last call, by name: a later one replaces an earlier one.
     *
     * @return array<string, array{system: string, first?: int, symbols?: list<string>, additive?: list<array{int, string}>, negative?: array{string, string}, prefix?: string, suffix?: string, range?: list<array{int|null, int|null}>|null, pad?: array{int, string}|null, fallback?: string, extends?: string, units?: list<string>, formal?: bool}>
     */
    public function takeCounterStyles(): array
    {
        $styles = $this->counterStyles;
        $this->counterStyles = [];

        return $styles;
    }

    /**
     * @return array<string, array<string, string>> The declarations of the @position-try rules parsed since the last call, by name
     */
    public function takePositionTries(): array
    {
        $tries = $this->positionTries;
        $this->positionTries = [];

        return $tries;
    }

    /**
     * The @keyframes parsed since the last call, by name.
     *
     * @return array<string, list<array{float, array<string, string>}>> Each keyframe: its offset from 0 to 1, and its declarations
     */
    public function takeKeyframes(): array
    {
        $keyframes = $this->keyframes;
        $this->keyframes = [];

        return $keyframes;
    }

    /**
     * Resolves a nested selector list against the selectors of its parent rule.
     *
     * "&" stands for the parent; a selector without "&" is a descendant of
     * it, or relative to it when it starts with a combinator. Like the
     * specification says, the parent behaves like :is(), unless it is a
     * single compound selector, whose text can be substituted as is.
     *
     * @param list<string> $parents
     *
     * @return list<string>
     */
    public static function nest(string $selectorList, array $parents): array
    {
        $parent = 1 === \count($parents) && self::isCompound($parents[0]) ? $parents[0] : ':is('.implode(', ', $parents).')';
        $selectors = [];
        foreach (Selector::splitTopLevel($selectorList) as $selector) {
            $selector = trim($selector);
            $replaced = self::replaceNestingSelector($selector, $parent);
            $selectors[] = $replaced ?? $parent.' '.$selector;
        }

        return $selectors;
    }

    /**
     * Evaluates an @supports condition.
     *
     * @internal
     */
    public function supports(string $condition): bool
    {
        $condition = trim($condition);
        if (preg_match('/^not\s+(.+)$/is', $condition, $m)) {
            return !$this->supports($m[1]);
        }
        foreach (['and', 'or'] as $operator) {
            $operands = self::splitOperator($condition, $operator);
            if (\count($operands) > 1) {
                $results = array_map($this->supports(...), $operands);

                return 'and' === $operator ? !\in_array(false, $results, true) : \in_array(true, $results, true);
            }
        }
        if (preg_match('/^selector\((.+)\)$/is', $condition, $m)) {
            try {
                Selector::parseList($m[1]);

                return true;
            } catch (InvalidArgumentException) {
                return false;
            }
        }
        if (str_starts_with($condition, '(') && self::closingParenthesis($condition, 0) === \strlen($condition) - 1) {
            $inner = trim(substr($condition, 1, -1));
            if (preg_match('/^(--[\w-]+|[a-z-]+)\s*:\s*(.+)$/is', $inner, $m) && !preg_match('/^(not|selector)\b/i', $inner)) {
                return $this->supportsDeclaration($m[1], preg_replace('/\s*!\s*important$/i', '', $m[2]));
            }

            return $this->supports($inner);
        }

        // Unknown functions, like font-tech(), are false
        return false;
    }

    /**
     * Splits a condition on a top-level "and" or "or".
     *
     * @return list<string>
     *
     * @internal
     */
    public static function splitOperator(string $condition, string $operator): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $length = \strlen($condition);
        $keyword = ' '.$operator.' ';
        for ($i = 0; $i < $length; ++$i) {
            $char = $condition[$i];
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            } elseif (0 === $depth && ctype_space($char) && 0 === strncasecmp(substr($condition, $i, \strlen($keyword)), $keyword, \strlen($keyword))) {
                $parts[] = trim(substr($condition, $start, $i - $start));
                $i += \strlen($keyword) - 1;
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($condition, $start));

        return $parts;
    }

    /**
     * Collects the @custom-media rules of a style sheet before its rules, as a query can use one defined after it.
     * A later rule of a name replaces an earlier one; the rules of earlier style sheets stay defined.
     */
    private function collectCustomMedia(string $css): void
    {
        if (!str_contains($css, '@custom-media') || !preg_match_all('/@custom-media\s+(--[\w-]+)\s+([^;{}]+);/i', $css, $matches, \PREG_SET_ORDER)) {
            return;
        }
        foreach ($matches as [, $name, $query]) {
            $this->customMedia[strtolower($name)] = trim($query);
        }
    }

    /**
     * Parses a style sheet, or the body of a style rule when $parents is set.
     *
     * In a rule body, declarations apply to the parent selectors; nested
     * rules and at-rules follow it, as CSS Nesting specifies.
     *
     * @param list<MediaQuery>  $media
     * @param list<string>      $imports Hrefs being imported, to stop cycles
     * @param list<string>|null $parents The selectors of the enclosing style rule
     *
     * @return list<CssRule>
     */
    private function parseBlock(string $css, array $media, array $imports, ?array $parents = null): array
    {
        // The style sheet at 0, its rules at 1
        if ($this->nesting > self::MAX_NESTING) {
            $this->report(\sprintf('CSS rules nested deeper than %d levels are ignored.', self::MAX_NESTING));

            return [];
        }
        ++$this->nesting;
        try {
            return $this->doParseBlock($css, $media, $imports, $parents);
        } finally {
            --$this->nesting;
        }
    }

    /**
     * @param list<MediaQuery>  $media
     * @param list<string>      $imports
     * @param list<string>|null $parents
     *
     * @return list<CssRule>
     */
    private function doParseBlock(string $css, array $media, array $imports, ?array $parents): array
    {
        $declarations = '';
        $items = [];
        $length = \strlen($css);
        $i = 0;
        $lastBrace = null;
        while ($i < $length) {
            // Skip whitespace and stray semicolons
            if (ctype_space($css[$i]) || ';' === $css[$i]) {
                ++$i;
                continue;
            }
            $braceAt = self::nextBrace($css, $i, $lastBrace);
            $semicolonAt = self::find($css, ';', $i);
            if ('@' === $css[$i]) {
                $name = strtolower(preg_match('/\G@([a-zA-Z-]+)/', $css, $m, 0, $i) ? $m[1] : '');
                // Statement at-rules: @import url(x.css) screen;
                if (null === $braceAt || null !== $semicolonAt && $semicolonAt < $braceAt) {
                    $end = $semicolonAt ?? $length;
                    if ('import' === $name && null === $parents) {
                        $items[] = ['import', trim(substr($css, $i + 7, $end - $i - 7))];
                    } elseif ('layer' === $name) {
                        // Declares the order of layers before their rules
                        $items[] = ['layers', trim(substr($css, $i + 6, $end - $i - 6))];
                    } elseif ('custom-media' === $name && null === $parents) {
                        // Collected before the rules, see collectCustomMedia()
                    } elseif (!isset(self::IGNORED_AT_RULES[$name])) {
                        $this->report(\sprintf('The "@%s" CSS at-rule is not supported%s.', $name, null === $parents ? '' : ' in a style rule'));
                    }
                    $i = $end + 1;
                    continue;
                }
                $end = self::matchingBrace($css, $braceAt);
                if ('layer' === $name) {
                    $items[] = ['layer', trim(substr($css, $i + 6, $braceAt - $i - 6)), substr($css, $braceAt + 1, $end - $braceAt - 1)];
                } elseif ('starting-style' === $name) {
                    $items[] = ['starting', '', substr($css, $braceAt + 1, $end - $braceAt - 1)];
                } elseif ('scope' === $name) {
                    $items[] = ['scope', trim(substr($css, $i + 6, $braceAt - $i - 6)), substr($css, $braceAt + 1, $end - $braceAt - 1)];
                } elseif ('container' === $name) {
                    $items[] = ['container', trim(substr($css, $i + 10, $braceAt - $i - 10)), substr($css, $braceAt + 1, $end - $braceAt - 1)];
                } elseif ('media' === $name || 'supports' === $name) {
                    $items[] = ['at', $name, trim(substr($css, $i + 1 + \strlen($name), $braceAt - $i - 1 - \strlen($name))), substr($css, $braceAt + 1, $end - $braceAt - 1)];
                } elseif ('function' === $name && null === $parents) {
                    $this->parseFunction(trim(substr($css, $i + 9, $braceAt - $i - 9)), substr($css, $braceAt + 1, $end - $braceAt - 1));
                } elseif ('property' === $name) {
                    $this->parseProperty(trim(substr($css, $i + 9, $braceAt - $i - 9)), substr($css, $braceAt + 1, $end - $braceAt - 1));
                } elseif ('position-try' === $name && null === $parents) {
                    $this->parsePositionTry(trim(substr($css, $i + 13, $braceAt - $i - 13)), substr($css, $braceAt + 1, $end - $braceAt - 1));
                } elseif ('counter-style' === $name) {
                    $this->parseCounterStyle(trim(substr($css, $i + 14, $braceAt - $i - 14)), substr($css, $braceAt + 1, $end - $braceAt - 1));
                } elseif ('keyframes' === $name || '-webkit-keyframes' === $name) {
                    $this->parseKeyframes(trim(substr($css, $i + 1 + \strlen($name), $braceAt - $i - 1 - \strlen($name))), substr($css, $braceAt + 1, $end - $braceAt - 1));
                } elseif (!isset(self::IGNORED_AT_RULES[$name])) {
                    $this->report(\sprintf('The "@%s" CSS at-rule is not supported.', $name));
                }
                $i = $end + 1;
                continue;
            }
            // In a rule body, what ends with ";" before any "{" is a declaration
            if (null !== $parents && (null === $braceAt || null !== $semicolonAt && $semicolonAt < $braceAt)) {
                $end = $semicolonAt ?? $length;
                $declarations .= substr($css, $i, $end - $i).';';
                $i = $end + 1;
                continue;
            }
            if (null === $braceAt) {
                $this->report(\sprintf('Unexpected "%s" at the end of the style sheet.', trim(substr($css, $i))));
                break;
            }
            $end = self::matchingBrace($css, $braceAt);
            $items[] = ['rule', trim(substr($css, $i, $braceAt - $i)), substr($css, $braceAt + 1, $end - $braceAt - 1)];
            $i = $end + 1;
        }
        $rules = [];
        // The declarations of a rule come before its nested rules
        if (null !== $parents && '' !== trim($declarations, "; \t\n\r")) {
            // Those of a style sheet, not the hints of the attributes of an <svg>, like its font-size="7"
            $parsed = $this->parseDeclarations($declarations, true);
            ++$this->order;
            foreach ($parents as $parent) {
                $rules[] = new CssRule(Selector::parse($parent), $parsed, $media, $this->order, $this->layerKey(), false, $this->containers);
            }
        }
        foreach ($items as $item) {
            match ($item[0]) {
                'import' => array_push($rules, ...$this->import($item[1], $media, $imports)),
                'layers' => array_map(fn (string $layer) => $this->enterLayer(trim($layer)), Selector::splitTopLevel($item[1])),
                'scope' => array_push($rules, ...$this->parseScope($item[1], $item[2], $media, $imports, $parents)),
                // The values transitions start from: rules of their own, out of the cascade
                'starting' => array_push($rules, ...array_map(static fn (CssRule $rule) => new CssRule($rule->selector, $rule->declarations, $rule->media, $rule->order, $rule->layer, $rule->userAgent, $rule->containers, $rule->scoped, true), $this->parseBlock($item[2], $media, $imports, $parents))),
                'container' => array_push($rules, ...$this->inContainer($item[1], fn () => $this->parseBlock($item[2], $media, $imports, $parents))),
                'layer' => array_push($rules, ...$this->inLayer('' === $item[1] ? null : $item[1], fn () => $this->parseBlock($item[2], $media, $imports, $parents))),
                'at' => array_push($rules, ...$this->parseAtRule($item[1], $item[2], $item[3], $media, $imports, $parents)),
                'rule' => array_push($rules, ...$this->parseStyleRule($item[1], $item[2], $media, $imports, $parents)),
            };
        }

        return $rules;
    }

    /**
     * Parses a @scope block: its rules match in the subtree of its root, not below its limit, like CSS.
     *
     * The root adds no specificity, and :scope stands for it; without a root, it is the element the style sheet is
     * in, or :root. The root itself matches the selectors of the block only through :scope.
     *
     * @param list<MediaQuery>  $media
     * @param list<string>      $imports
     * @param list<string>|null $parents
     *
     * @return list<CssRule>
     */
    private function parseScope(string $prelude, string $body, array $media, array $imports, ?array $parents): array
    {
        if (!preg_match('/^(?:\((.*?)\))?\s*(?:to\s*\((.*)\))?$/s', trim($prelude), $m)) {
            $this->report(\sprintf('Invalid @scope prelude "%s".', $prelude));

            return [];
        }
        $root = trim($m[1] ?? '');
        $limit = trim($m[2] ?? '');
        if ('' === $root) {
            $root = null !== $parents ? implode(', ', $parents) : $this->implicitScope ?? ':root';
        }
        try {
            Selector::parseList($root);
            if ('' !== $limit) {
                Selector::parseList($limit);
            }
        } catch (InvalidArgumentException $e) {
            $this->report(\sprintf('Invalid @scope prelude "%s": %s', $prelude, $e->getMessage()));

            return [];
        }
        // Nested under the root, :scope being it, with no specificity of its own
        $scoped = preg_replace('/:scope\b/i', '&', $body) ?? $body;
        $rules = array_map(static fn (CssRule $rule) => new CssRule($rule->selector, $rule->declarations, $rule->media, $rule->order, $rule->layer, $rule->userAgent, $rule->containers, true), $this->parseBlock($scoped, $media, $imports, [':where('.$root.')']));
        if ('' === $limit) {
            return $rules;
        }
        // Not in the limit, nor below it: the subject of each selector is excluded there
        $exclusion = ':where(:not(:where('.$root.') :where('.$limit.'), :where('.$root.') :where('.$limit.') *))';
        $limited = [];
        foreach ($rules as $rule) {
            $source = $rule->selector->source;
            $at = null !== $rule->selector->pseudoElement ? strrpos($source, '::') : false;
            $selector = false === $at ? $source.$exclusion : substr($source, 0, $at).$exclusion.substr($source, $at);
            try {
                $limited[] = new CssRule(Selector::parse($selector), $rule->declarations, $rule->media, $rule->order, $rule->layer, $rule->userAgent, $rule->containers, true);
            } catch (InvalidArgumentException) {
                $limited[] = $rule;
            }
        }

        return $limited;
    }

    /**
     * Parses a @counter-style block, see CounterStyles::parse().
     */
    private function parseCounterStyle(string $name, string $body): void
    {
        $descriptors = [];
        foreach ($this->parseDeclarations($body) as $declaration) {
            $descriptors[strtolower($declaration->property)] = trim($declaration->value);
        }
        $name = strtolower($name);
        if (null === $style = CounterStyles::parse($name, $descriptors)) {
            $this->report(\sprintf('Invalid @counter-style "%s": it needs a name, a system and the symbols of its system.', $name));

            return;
        }
        if (\count($this->counterStyles) < 1000 || isset($this->counterStyles[$name])) {
            unset($this->counterStyles[$name]);
            $this->counterStyles[$name] = $style;
        }
    }

    /**
     * Parses a @property block: the syntax, inherits and initial-value of a custom property; invalid without inherits,
     * or without an initial value unless any value goes ("*"), like CSS.
     */
    /**
     * Parses a @function rule: its parameters with their types and defaults, its return type, and its body of local
     * custom properties and result descriptors, in @media and @supports rules too.
     */
    private function parseFunction(string $prelude, string $body): void
    {
        if (!preg_match('/^(--[\w-]+)\s*\((.*)\)\s*(?:returns\s+(.+))?$/is', $prelude, $m) || \count($this->functions) >= 1000) {
            $this->report(\sprintf('Invalid @function "%s".', $prelude));

            return;
        }
        $params = [];
        foreach ('' === trim($m[2]) ? [] : Selector::splitTopLevel($m[2]) as $param) {
            // --name, its type (<length>, type(<length> | auto), *) and its default after a colon
            if (!preg_match('/^\s*(--[\w-]+)\s*(type\(.*\)|<[\w-]+>(?:\s*\|\s*<[\w-]+>)*|\*)?\s*(?::\s*(.*))?$/s', $param, $p)) {
                $this->report(\sprintf('Invalid parameter "%s" of @function "%s".', trim($param), $m[1]));

                return;
            }
            $params[] = [$p[1], self::functionType($p[2] ?? ''), isset($p[3]) && '' !== trim($p[3]) ? trim($p[3]) : null];
        }
        $statements = [];
        $this->functionBody(self::stripComments($body), [], $statements);
        $this->functions[$m[1]] = ['params' => $params, 'returns' => self::functionType($m[3] ?? ''), 'body' => $statements];
    }

    /**
     * The syntax of a type of @function: <length>, type(<length> | auto), or null for any.
     */
    private static function functionType(string $type): ?string
    {
        $type = trim($type);
        if (preg_match('/^type\((.*)\)$/is', $type, $m)) {
            $type = trim($m[1]);
        }

        return '' === $type || '*' === $type ? null : $type;
    }

    /**
     * The descriptors of a @function body in order, each with the @media or @supports condition around it.
     *
     * @param list<array{string, string}>                              $conditions The @media and @supports tests around them, all of which pass
     * @param list<array{list<array{string, string}>, string, string}> $statements
     */
    private function functionBody(string $body, array $conditions, array &$statements): void
    {
        $length = \strlen($body);
        $i = 0;
        $lastBrace = null;
        while ($i < $length) {
            if (ctype_space($body[$i]) || ';' === $body[$i]) {
                ++$i;
                continue;
            }
            $braceAt = self::nextBrace($body, $i, $lastBrace);
            $semicolonAt = self::find($body, ';', $i);
            if ('@' === $body[$i] && null !== $braceAt && (null === $semicolonAt || $braceAt < $semicolonAt)) {
                $end = self::matchingBrace($body, $braceAt);
                $name = strtolower(preg_match('/\G@([a-z-]+)/i', $body, $at, 0, $i) ? $at[1] : '');
                $test = trim(substr($body, $i + 1 + \strlen($name), $braceAt - $i - 1 - \strlen($name)));
                if ('media' === $name || 'supports' === $name) {
                    $this->functionBody(substr($body, $braceAt + 1, $end - $braceAt - 1), [...$conditions, [$name, $test]], $statements);
                } else {
                    $this->report(\sprintf('The "@%s" CSS at-rule is not supported in a @function.', $name));
                }
                $i = $end + 1;
                continue;
            }
            $end = $semicolonAt ?? $length;
            foreach ($this->parseDeclarations(substr($body, $i, $end - $i)) as $declaration) {
                $property = str_starts_with($declaration->property, '--') ? $declaration->property : strtolower($declaration->property);
                if ('result' === $property || str_starts_with($property, '--')) {
                    $statements[] = [$conditions, $property, trim($declaration->value)];
                }
            }
            $i = $end + 1;
        }
    }

    private function parseProperty(string $name, string $body): void
    {
        $descriptors = [];
        foreach ($this->parseDeclarations($body) as $declaration) {
            $descriptors[strtolower($declaration->property)] = trim($declaration->value);
        }
        $syntax = trim($descriptors['syntax'] ?? '', " \t\n\"'");
        $inherits = strtolower($descriptors['inherits'] ?? '');
        $initial = $descriptors['initial-value'] ?? null;
        // The initial value must be of the syntax, and computationally independent: no var()
        $typedInitial = null === $initial || '*' === $syntax || !str_contains($initial, 'var(') && $this->matchesSyntax($syntax, $initial);
        if (!preg_match('/^--[\w-]+$/', $name) || '' === $syntax || !\in_array($inherits, ['true', 'false'], true) || null === $initial && '*' !== $syntax || !$typedInitial || \count($this->registered) >= 1000) {
            $this->report(\sprintf('Invalid @property "%s": it needs a name, a syntax, inherits, and an initial value unless its syntax is "*".', $name));

            return;
        }
        $this->registered[$name] = ['true' === $inherits, $initial, $syntax];
    }

    /**
     * Parses a @position-try rule: a dashed name, and the declarations that place an anchored element otherwise.
     */
    private function parsePositionTry(string $name, string $body): void
    {
        if (!preg_match('/^--[a-z_][\w-]*$/i', $name)) {
            $this->report(\sprintf('Invalid @position-try name "%s".', $name));

            return;
        }
        $declarations = [];
        foreach ($this->parseDeclarations($body) as $declaration) {
            // !important is invalid in it, like CSS
            if (!$declaration->important) {
                $declarations[$declaration->property] = $declaration->value;
            }
        }
        $this->positionTries[strtolower($name)] = $declarations;
    }

    /**
     * Parses a @keyframes block: from, to and percentages, each with its declarations.
     */
    private function parseKeyframes(string $name, string $body): void
    {
        $name = trim($name, " \t\n\"'");
        if (!preg_match('/^-?[a-z_][\w-]*$/i', $name) || \in_array(strtolower($name), ['none', 'initial', 'inherit', 'unset'], true)) {
            $this->report(\sprintf('Invalid @keyframes name "%s".', $name));

            return;
        }
        $frames = [];
        // A block left open ends with the body: nothing after it
        for ($i = 0; $i < \strlen($body) && false !== $open = strpos($body, '{', $i); $i = $close + 1) {
            $close = self::matchingBrace($body, $open);
            $declarations = [];
            foreach ($this->parseDeclarations(substr($body, $open + 1, $close - $open - 1)) as $declaration) {
                // Like CSS, !important declarations in keyframes are ignored
                if (!$declaration->important) {
                    $declarations[str_starts_with($declaration->property, '--') ? $declaration->property : strtolower($declaration->property)] = $declaration->value;
                }
            }
            foreach (explode(',', substr($body, $i, $open - $i)) as $selector) {
                $selector = strtolower(trim($selector));
                $offset = match (true) {
                    'from' === $selector => 0.0,
                    'to' === $selector => 1.0,
                    1 === preg_match('/^(\d{1,3}(?:\.\d+)?)%$/', $selector, $m) && (float) $m[1] <= 100 => (float) $m[1] / 100,
                    default => null,
                };
                if (null === $offset) {
                    continue;
                }
                // Keyframes at the same offset merge, the later declarations winning, like CSS; at most 100 of them
                $key = (string) $offset;
                if (!isset($frames[$key]) && \count($frames) >= 100) {
                    break 2;
                }
                $frames[$key] = [$offset, $declarations + ($frames[$key][1] ?? [])];
            }
        }
        $frames = array_values($frames);
        usort($frames, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        $this->keyframes[$name] = $frames;
    }

    /**
     * Parses @media and @supports blocks.
     *
     * @param list<MediaQuery>  $media
     * @param list<string>      $imports
     * @param list<string>|null $parents
     *
     * @return list<CssRule>
     */
    private function parseAtRule(string $name, string $prelude, string $body, array $media, array $imports, ?array $parents): array
    {
        if ('media' === $name) {
            return $this->parseBlock($body, [...$media, $this->parseMedia($prelude, $this->customMedia)], $imports, $parents);
        }

        return $this->supports($prelude) ? $this->parseBlock($body, $media, $imports, $parents) : [];
    }

    /**
     * @param list<MediaQuery>  $media
     * @param list<string>      $imports
     * @param list<string>|null $parents
     *
     * @return list<CssRule>
     */
    private function parseStyleRule(string $selectorList, string $body, array $media, array $imports, ?array $parents): array
    {
        try {
            $selectors = null === $parents ? Selector::splitTopLevel($selectorList) : self::nest($selectorList, $parents);
            // An invalid selector drops the whole rule, nested rules included
            foreach ($selectors as $selector) {
                Selector::parse($selector);
            }
        } catch (InvalidArgumentException $e) {
            $this->report(\sprintf('Invalid selector "%s": %s', $selectorList, $e->getMessage()));

            return [];
        }

        return $this->parseBlock($body, $media, $imports, $selectors);
    }

    private static function isCompound(string $selector): bool
    {
        $depth = 0;
        $length = \strlen($selector);
        for ($i = 0; $i < $length; ++$i) {
            $char = $selector[$i];
            if ('"' === $char || "'" === $char) {
                $i = self::skipString($selector, $i);
            } elseif ('(' === $char || '[' === $char) {
                ++$depth;
            } elseif (')' === $char || ']' === $char) {
                --$depth;
            } elseif (0 === $depth && (ctype_space($char) || '>' === $char || '+' === $char || '~' === $char || ',' === $char)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string|null The selector with "&" replaced, null when it has none
     */
    private static function replaceNestingSelector(string $selector, string $parent): ?string
    {
        $result = '';
        $found = false;
        $length = \strlen($selector);
        for ($i = 0; $i < $length; ++$i) {
            $char = $selector[$i];
            if ('"' === $char || "'" === $char) {
                $end = self::skipString($selector, $i);
                $result .= substr($selector, $i, $end - $i + 1);
                $i = $end;
            } elseif ('&' === $char) {
                $result .= $parent;
                $found = true;
            } else {
                $result .= $char;
            }
        }

        return $found ? $result : null;
    }

    private static function closingParenthesis(string $value, int $open): ?int
    {
        $depth = 0;
        $length = \strlen($value);
        for ($i = $open; $i < $length; ++$i) {
            if ('(' === $value[$i]) {
                ++$depth;
            } elseif (')' === $value[$i] && 0 === --$depth) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<MediaQuery> $media
     * @param list<string>     $imports
     *
     * @return list<CssRule>
     */
    private function import(string $prelude, array $media, array $imports): array
    {
        if (!preg_match('/^(?:url\(\s*)?(["\']?)([^"\')]+)\1\s*\)?\s*(.*)$/s', $prelude, $m)) {
            $this->report(\sprintf('Invalid @import "%s".', $prelude));

            return [];
        }
        $href = $m[2];
        if (null === $this->loader) {
            $this->report(\sprintf('Cannot import "%s": no stylesheet loader is configured.', $href));

            return [];
        }
        if (\in_array($href, $imports, true)) {
            $this->report(\sprintf('The stylesheet "%s" imports itself.', $href));

            return [];
        }
        if (++$this->imports > self::MAX_IMPORTS) {
            $this->report(\sprintf('Cannot import "%s": a style sheet cannot import more than %d others.', $href, self::MAX_IMPORTS));

            return [];
        }
        // @import url(x.css) layer(name) supports(...) media
        $rest = trim($m[3]);
        $layer = false;
        if (preg_match('/^layer(?:\(\s*([\w.-]+)\s*\))?(?=\s|$)/i', $rest, $l)) {
            $layer = $l[1] ?? null;
            $rest = trim(substr($rest, \strlen($l[0])));
        }
        if ('' !== $mediaList = $rest) {
            $media[] = $this->parseMedia($mediaList, $this->customMedia);
        }
        if (null === $css = $this->imported[$href] ?? null) {
            try {
                $css = $this->loader->load($href);
            } catch (\InvalidArgumentException $e) {
                $this->report($e->getMessage());

                return [];
            }
            // Counted as loaded, before any work on it
            $this->importedBytes[$href] = \strlen($css);
            if ($this->bytes + \strlen($css) <= self::MAX_BYTES) {
                $css = self::stripComments($css);
            }
            $this->imported[$href] = $css;
        }
        if (($this->bytes += $this->importedBytes[$href]) > self::MAX_BYTES) {
            // Not kept either: an import of it again is refused by its size alone
            $this->imported[$href] = '';
            $this->report(\sprintf('Cannot import "%s": a style sheet and those it imports cannot have more than %d bytes.', $href, self::MAX_BYTES));

            return [];
        }
        $this->collectCustomMedia($css);
        $parse = fn () => $this->parseBlock($css, $media, [...$imports, $href]);

        return false === $layer ? $parse() : $this->inLayer($layer, $parse);
    }

    /**
     * Parses in a layer: a named one, nested in the current one, or a new anonymous one.
     *
     * @param \Closure(): list<CssRule> $parse
     *
     * @return list<CssRule>
     */
    private function inLayer(?string $name, \Closure $parse): array
    {
        $path = $this->layerPath;
        $layerName = $this->layerName;
        try {
            // a.b is b nested in a
            foreach (null === $name ? ["\x00".++$this->anonymousLayers] : explode('.', $name) as $part) {
                $this->enterLayer($part, true);
            }

            return $parse();
        } finally {
            $this->layerPath = $path;
            $this->layerName = $layerName;
        }
    }

    /**
     * Parses in an @container block; an invalid condition drops the block, like CSS.
     *
     * @param \Closure(): list<CssRule> $parse
     *
     * @return list<CssRule>
     */
    private function inContainer(string $prelude, \Closure $parse): array
    {
        try {
            $query = $this->parseContainer($prelude);
        } catch (InvalidArgumentException $e) {
            $this->report($e->getMessage());

            return [];
        }
        if (null === $query) {
            return [];
        }
        $containers = $this->containers;
        $this->containers[] = $query;
        try {
            return $parse();
        } finally {
            $this->containers = $containers;
        }
    }

    /**
     * Registers a layer in the current one; with $enter, the layer becomes the current one.
     */
    private function enterLayer(string $name, bool $enter = false): void
    {
        if (!$enter) {
            // @layer a.b; registers a, then b in a
            $path = $this->layerPath;
            $layerName = $this->layerName;
            foreach (explode('.', $name) as $part) {
                $this->enterLayer(trim($part), true);
            }
            $this->layerPath = $path;
            $this->layerName = $layerName;

            return;
        }
        $ranks = $this->layerRanks[$this->layerName] ?? [];
        $rank = $ranks[$name] ??= min(\count($ranks), 9998);
        $this->layerRanks[$this->layerName] = $ranks;
        $this->layerPath[] = $rank;
        $this->layerName .= '.'.$name;
        $this->layersMet[$this->layerName] = true;
    }

    /**
     * The layers the style sheets parsed since the last call declared or used, by their dotted name (".a.b"), in the
     * order they were met: for the style sheet to order the layers again by its sources, after one is removed.
     *
     * @return list<string>
     *
     * @internal
     */
    public function takeLayers(): array
    {
        $layers = array_keys($this->layersMet);
        $this->layersMet = [];

        return $layers;
    }

    /**
     * The dotted name of the layer of a key of a rule this parser returned, '' for none; null for a key it did not.
     *
     * @internal
     */
    public function getLayerName(string $key): ?string
    {
        return $this->layerNames[$key] ?? null;
    }

    /**
     * Sorts like the cascade: earlier layers first, the rules of a layer after its sublayers, unlayered rules last.
     */
    private function layerKey(): string
    {
        $key = 'L'.implode('', array_map(static fn (int $rank) => \sprintf('%04d', $rank), $this->layerPath)).'9999';
        $this->layerNames[$key] ??= $this->layerName;

        return $key;
    }

    protected function parseMedia(string $query, array $customMedia): mixed
    {
        return MediaQuery::parse($query, $customMedia);
    }

    protected function parseContainer(string $query): mixed
    {
        return ContainerQuery::parse($query);
    }

    protected function supportsDeclaration(string $property, string $value): bool
    {
        $property = strtolower($property);
        $value = strtolower(trim($value));
        if (str_starts_with($property, '--')) {
            return true;
        }
        if ('display' === $property) {
            return \in_array(self::display($value), ['none', 'contents', 'block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'table', 'inline-table', 'table-row', 'table-cell', 'table-row-group', 'table-header-group', 'table-footer-group', 'flow-root'], true);
        }
        $keywords = match ($property) {
            'position' => ['static', 'relative', 'absolute', 'fixed'],
            'overflow' => ['visible', 'hidden', 'clip', 'scroll', 'auto'],
            'font-style' => ['normal', 'italic', 'oblique'],
            'text-align' => ['left', 'right', 'center', 'start', 'end'],
            'white-space' => ['normal', 'pre', 'pre-wrap', 'nowrap'],
            'flex-direction' => ['row', 'column', 'row-reverse', 'column-reverse'],
            'align-items' => ['normal', 'stretch', 'start', 'end', 'flex-start', 'flex-end', 'center'],
            'justify-content' => ['normal', 'start', 'end', 'flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly'],
            default => [],
        };
        if ([] !== $keywords) {
            return \in_array($value, $keywords, true);
        }
        if (\in_array($property, ['opacity', 'flex-grow', 'flex-shrink', 'font-weight'], true)) {
            return is_numeric($value);
        }
        if (\in_array($property, ['color', 'background', 'background-color'], true)) {
            return 'currentcolor' === $value || \is_array(ColorValue::parse($value));
        }

        if (!\in_array($property, ['width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'margin', 'padding', 'font-size', 'line-height', 'top', 'right', 'bottom', 'left', 'gap', 'flex-basis'], true)) {
            return false;
        }
        if (\in_array($value, ['auto', 'min-content', 'max-content'], true)) {
            return \in_array($property, ['width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'flex-basis', 'margin', 'top', 'right', 'bottom', 'left'], true);
        }
        $parts = ValueTokenizer::split($value);
        if ([] === $parts || \count($parts) > (\in_array($property, ['margin', 'padding'], true) ? 4 : ('gap' === $property ? 2 : 1))) {
            return false;
        }
        foreach ($parts as $part) {
            if (null === Length::evaluate($part, static fn (string $token): ?float => preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(px|%|em|rem|ch|lh|vw|vh|vmin|vmax)?$/', $token) ? (float) $token : null)) {
                return false;
            }
        }

        return true;
    }

    protected function matchesSyntax(string $syntax, string $value): bool
    {
        return match ($syntax) {
            '*' => true,
            '<number>' => is_numeric($value),
            '<color>' => \is_array(ColorValue::parse($value)),
            '<integer>' => (bool) preg_match('/^[+-]?\d+$/', $value),
            '<length>' => (bool) preg_match('/^(?:0|[+-]?(?:\d+\.?\d*|\.\d+)(?:px|em|rem|ch|ex|lh|rlh|vw|vh|vmin|vmax|pt|pc|cm|mm|in))$/i', $value),
            '<percentage>' => (bool) preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)%$/', $value),
            default => false,
        };
    }

    protected function report(string $message): void
    {
        if ($this->strict) {
            throw new InvalidArgumentException($message);
        }
        // Once each for the style sheet, like the declarations: a framework repeats the same at-rule or selector many
        // times; past 1000 problems, the others are not reported
        if (isset($this->reported[$message]) || \count($this->reported) >= 1000) {
            return;
        }
        $this->reported[$message] = true;
        $this->logger?->warning($message);
    }

    private static function stripComments(string $css): string
    {
        return DeclarationParser::stripComments($css);
    }

    /**
     * Finds a character outside strings and parentheses.
     */
    /**
     * @param int|null $stop Where the search ended: the character found, the } that ends the block, or the end
     */
    private static function find(string $css, string $char, int $offset, ?int &$stop = null): ?int
    {
        $length = \strlen($css);
        $depth = 0;
        // Straight to the next character that counts: a large style sheet is mostly others
        $mask = "\\\"'()}".$char;
        for ($i = $offset; $i < $length; ++$i) {
            $i += strcspn($css, $mask, $i);
            if ($i >= $length) {
                break;
            }
            $c = $css[$i];
            // An escape, like the \' and \( of the selectors of Tailwind: the character it escapes counts for nothing
            if ('\\' === $c) {
                ++$i;
            } elseif ('"' === $c || "'" === $c) {
                $i = self::skipString($css, $i);
            } elseif ('(' === $c) {
                ++$depth;
            } elseif (')' === $c) {
                --$depth;
            } elseif ($char === $c && 0 === $depth) {
                return $stop = $i;
            } elseif ('}' === $c && '}' !== $char) {
                $stop = $i;

                return null;
            }
        }
        $stop = $length;

        return null;
    }

    /**
     * The next { from a statement, the search before reused while it is ahead: a block of many declarations, like the
     * :root of hundreds of custom properties of Bulma, is not searched to its end again for each of them.
     *
     * @param-out array{int, ?int, ?int} $last
     *
     * @param array{int, ?int, ?int}|null $last The offset searched from, and the brace found or null, then where it stopped
     */
    private static function nextBrace(string $css, int $offset, ?array &$last): ?int
    {
        if (null !== $last && $offset >= $last[0] && $offset <= $last[2]) {
            return $last[1];
        }
        $brace = self::find($css, '{', $offset, $stop);
        $last = [$offset, $brace, $stop];

        return $brace;
    }

    private static function matchingBrace(string $css, int $open): int
    {
        $length = \strlen($css);
        $depth = 0;
        for ($i = $open; $i < $length; ++$i) {
            $i += strcspn($css, "\\\"'{}", $i);
            if ($i >= $length) {
                break;
            }
            $c = $css[$i];
            if ('\\' === $c) {
                ++$i;
            } elseif ('"' === $c || "'" === $c) {
                $i = self::skipString($css, $i);
            } elseif ('{' === $c) {
                ++$depth;
            } elseif ('}' === $c && 0 === --$depth) {
                return $i;
            }
        }

        // An unclosed block ends with the style sheet
        return $length;
    }

    private static function skipString(string $css, int $offset): int
    {
        $quote = $css[$offset];
        $length = \strlen($css);
        for ($i = $offset + 1; $i < $length; ++$i) {
            // Straight to the next escape or quote, like over a long data: URL
            $i += strcspn($css, '\\'.$quote, $i);
            if ($i >= $length) {
                break;
            }
            if ('\\' === $css[$i]) {
                ++$i;
            } elseif ($quote === $css[$i]) {
                return $i;
            }
        }

        return $length;
    }
}
