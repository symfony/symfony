<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Compatibility\Support;
use Symfony\Component\HtmlRenderer\Css\Declaration;
use Symfony\Component\HtmlRenderer\Css\StylesheetLoaderInterface;

/**
 * Adapts shared CSS parsing to the terminal renderer's supported declarations.
 *
 * @internal
 */
final class CssParser extends \Symfony\Component\HtmlRenderer\Css\CssParser
{
    private const IGNORED_AT_RULES = ['font-face' => 'The terminal draws text with its own font: fonts cannot be loaded.', 'font-feature-values' => 'The terminal chooses the glyphs of its font.', 'font-palette-values' => 'The terminal chooses the glyphs of its font.', 'page' => 'Nothing is printed.', 'namespace' => 'Elements are in the HTML namespace: a prefix declared or not matches none of them in selectors, *| matches them all.', 'charset' => 'Style sheets are read as UTF-8.', 'view-transition' => 'Changes are drawn at once, without view transitions; see transition.'];

    public function __construct(?StylesheetLoaderInterface $loader = null, ?LoggerInterface $logger = null, bool $strict = false, private ?DeclarationMapper $mapper = null)
    {
        parent::__construct($loader, $logger, $strict);
    }

    public function parseDeclarations(string $css, bool $validate = false): array
    {
        $declarations = [];
        foreach (\Symfony\Component\HtmlRenderer\Css\DeclarationParser::parse($css, $this->report(...)) as $declaration) {
            $property = $declaration->property;
            $value = 'display' === $property ? self::display($declaration->value) : $declaration->value;
            if ($validate && null !== $this->mapper && $this->mapper->rejects($property, $value)) {
                $this->report(\sprintf('Invalid value "%s" for the "%s" CSS property.', $value, $property));
                continue;
            }
            $declarations[] = new Declaration($property, $value, $declaration->important);
            if ('border-spacing' === $property && '' !== $across = Value::split($value)[0] ?? '') {
                $declarations[] = new Declaration('--tui-cell-spacing', $across, $declaration->important);
            }
        }

        return $declarations;
    }

    public static function getSupport(): array
    {
        $support = ['css.at-rules.custom-media' => Support::supported('A name for a media query list, tested by (--name) in @media and @import, defined before or after its use; true and false are queries that always and never match; an undefined name never matches.'), 'css.at-rules.position-try' => Support::partial('Named fallbacks for position-try-fallbacks: their insets (with anchor()), position-area and position-anchor place the element when its own place would leave the screen, at the width and height they set (lengths or anchor-size()), with which it must fit; their other sizes and margins are ignored; a later rule of a name replaces an earlier one.'), ...array_combine(array_map(static fn (string $name) => 'css.at-rules.'.$name, array_keys(self::IGNORED_AT_RULES)), array_map(Support::ignored(...), self::IGNORED_AT_RULES)), 'css.at-rules.import' => Support::supported('Resolved with the stylesheet loader; a media list is honored.'), 'css.at-rules.supports' => Support::supported('A declaration is supported when it has an effect; selector() is supported.'), 'css.at-rules.function' => Support::partial('Custom functions, like --double(2ch): typed parameters with defaults, local custom properties, the last result, in @media and @supports rules too, a return type; called in any property and in custom properties, the custom properties of the element seen; a call that fails, a cycle or a value not of its type make the declaration invalid at computed-value time.'), 'css.at-rules.container.scroll-state' => Support::supported('scroll-state() queries on the nearest scroll-state container (container-type: scroll-state), by name or not, as it was drawn in the last frame: stuck (a sticky element stuck to the top or the bottom), snapped (a snap target its scroll container rests on, x or y), scrollable (the sides a scroll container can be scrolled to) and scrolled (the side it was last scrolled to), with not, and or or.'), 'css.at-rules.container' => Support::partial('Size queries (width, height, inline-size, block-size, aspect-ratio, orientation, with the range syntax) on the nearest container that can answer them, by name or not, measured on the last frame; style() queries test the computed values of the nearest ancestor, or of the nearest one with the name: style(--x: value), style(--x), with not, and or or; size and style conditions joined by and both pass, each on the nearest container it applies to, or on the one of the name.'), 'css.at-rules.starting-style' => Support::partial('The values the transitions of an element start from when it is first shown, or shown again after display: none; nested in rules too.'), 'css.at-rules.property' => Support::partial('Registers a custom property: inherits: false keeps it on the element that sets it, initial-value is its value where nothing sets it, and a typed syntax (<color>, <length>, <number>, <percentage>...) lets it be transitioned and animated; a value not of the syntax is invalid at computed-value time, inherited or initial, and an initial value not of it invalidates the registration.'), 'css.at-rules.scope' => Support::partial('A root and a limit, or the element of the <style> without a root: the rules match in its subtree, not in or below the limit, with no specificity added, :scope being the root; the root itself matches through :scope only.'), 'css.at-rules.keyframes' => Support::partial('from, to and percentages; colors and numbers are interpolated, also in lists and functions of the same shape (1ch 2ch, translate(), a shadow and its color, a gradient), lengths of different units through calc(); other values change halfway, like CSS discrete animation.'), 'css.at-rules.counter-style' => Support::supported('Every system (cyclic, numeric, alphabetic, symbolic, additive, fixed, extends) with symbols, additive-symbols, negative, prefix, suffix, range, pad and fallback, for list-style-type, ::marker and counter(); symbols are text, not images; a later rule of a name replaces an earlier one.'), 'css.at-rules.layer' => Support::supported('Named, nested and anonymous layers, statements and @import layer(); unlayered rules win over layered ones, and !important reverses the order, like CSS.'), 'css.properties.important' => Support::supported(), 'css.selectors.nesting' => Support::supported('Nested rules, the & selector and nested @media and @supports.')];

        return $support;
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
        return ($this->mapper ??= new DeclarationMapper())->supports($property, $value);
    }

    protected function matchesSyntax(string $syntax, string $value): bool
    {
        return PropertySyntax::matches($syntax, $value, new MediaContext());
    }
}
