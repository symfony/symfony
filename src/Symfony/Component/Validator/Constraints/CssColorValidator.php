<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
class CssColorValidator extends ConstraintValidator
{
    private const PATTERN_HEX_LONG = '/^#[0-9a-f]{6}$/i';
    private const PATTERN_HEX_LONG_WITH_ALPHA = '/^#[0-9a-f]{8}$/i';
    private const PATTERN_HEX_SHORT = '/^#[0-9a-f]{3}$/i';
    private const PATTERN_HEX_SHORT_WITH_ALPHA = '/^#[0-9a-f]{4}$/i';
    // List comes from https://www.w3.org/wiki/CSS/Properties/color/keywords#Basic_Colors
    private const PATTERN_BASIC_NAMED_COLORS = '/^(black|silver|gray|white|maroon|red|purple|fuchsia|green|lime|olive|yellow|navy|blue|teal|aqua)$/i';
    // List comes from https://www.w3.org/wiki/CSS/Properties/color/keywords#Extended_colors
    private const PATTERN_EXTENDED_NAMED_COLORS = '/^(aliceblue|antiquewhite|aqua|aquamarine|azure|beige|bisque|black|blanchedalmond|blue|blueviolet|brown|burlywood|cadetblue|chartreuse|chocolate|coral|cornflowerblue|cornsilk|crimson|cyan|darkblue|darkcyan|darkgoldenrod|darkgray|darkgreen|darkgrey|darkkhaki|darkmagenta|darkolivegreen|darkorange|darkorchid|darkred|darksalmon|darkseagreen|darkslateblue|darkslategray|darkslategrey|darkturquoise|darkviolet|deeppink|deepskyblue|dimgray|dimgrey|dodgerblue|firebrick|floralwhite|forestgreen|fuchsia|gainsboro|ghostwhite|gold|goldenrod|gray|green|greenyellow|grey|honeydew|hotpink|indianred|indigo|ivory|khaki|lavender|lavenderblush|lawngreen|lemonchiffon|lightblue|lightcoral|lightcyan|lightgoldenrodyellow|lightgray|lightgreen|lightgrey|lightpink|lightsalmon|lightseagreen|lightskyblue|lightslategray|lightslategrey|lightsteelblue|lightyellow|lime|limegreen|linen|magenta|maroon|mediumaquamarine|mediumblue|mediumorchid|mediumpurple|mediumseagreen|mediumslateblue|mediumspringgreen|mediumturquoise|mediumvioletred|midnightblue|mintcream|mistyrose|moccasin|navajowhite|navy|oldlace|olive|olivedrab|orange|orangered|orchid|palegoldenrod|palegreen|paleturquoise|palevioletred|papayawhip|peachpuff|peru|pink|plum|powderblue|purple|red|rosybrown|royalblue|saddlebrown|salmon|sandybrown|seagreen|seashell|sienna|silver|skyblue|slateblue|slategray|slategrey|snow|springgreen|steelblue|tan|teal|thistle|tomato|turquoise|violet|wheat|white|whitesmoke|yellow|yellowgreen)$/i';
    // List comes from https://drafts.csswg.org/css-color/#css-system-colors
    private const PATTERN_SYSTEM_COLORS = '/^(Canvas|CanvasText|LinkText|VisitedText|ActiveText|ButtonFace|ButtonText|ButtonBorder|Field|FieldText|Highlight|HighlightText|SelectedItem|SelectedItemText|Mark|MarkText|GrayText)$/i';
    private const PATTERN_KEYWORDS = '/^(transparent|currentColor)$/i';
    private const PATTERN_RGB = '/^rgb\(\s*(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d),\s*(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d),\s*(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d)\s*\)$/i';
    private const PATTERN_RGBA = '/^rgba\(\s*(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d),\s*(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d),\s*(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d),\s*(0|0?\.\d+|1(\.0)?)\s*\)$/i';
    private const PATTERN_HSL = '/^hsl\(\s*(0|360|35\d|3[0-4]\d|[12]\d\d|0?\d?\d),\s*(0|100|\d{1,2})%,\s*(0|100|\d{1,2})%\s*\)$/i';
    private const PATTERN_HSLA = '/^hsla\(\s*(0|360|35\d|3[0-4]\d|[12]\d\d|0?\d?\d),\s*(0|100|\d{1,2})%,\s*(0|100|\d{1,2})%,\s*(0|0?\.\d+|1(\.0)?)\s*\)$/i';

    private const COLOR_PATTERNS = [
        CssColor::HEX_LONG => self::PATTERN_HEX_LONG,
        CssColor::HEX_LONG_WITH_ALPHA => self::PATTERN_HEX_LONG_WITH_ALPHA,
        CssColor::HEX_SHORT => self::PATTERN_HEX_SHORT,
        CssColor::HEX_SHORT_WITH_ALPHA => self::PATTERN_HEX_SHORT_WITH_ALPHA,
        CssColor::BASIC_NAMED_COLORS => self::PATTERN_BASIC_NAMED_COLORS,
        CssColor::EXTENDED_NAMED_COLORS => self::PATTERN_EXTENDED_NAMED_COLORS,
        CssColor::SYSTEM_COLORS => self::PATTERN_SYSTEM_COLORS,
        CssColor::KEYWORDS => self::PATTERN_KEYWORDS,
        CssColor::RGB => self::PATTERN_RGB,
        CssColor::RGBA => self::PATTERN_RGBA,
        CssColor::HSL => self::PATTERN_HSL,
        CssColor::HSLA => self::PATTERN_HSLA,
    ];

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof CssColor) {
            throw new UnexpectedTypeException($constraint, CssColor::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $formats = array_flip((array) $constraint->formats);
        $formatRegexes = array_intersect_key(self::COLOR_PATTERNS, $formats);

        foreach ($formatRegexes as $regex) {
            if (preg_match($regex, (string) $value)) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->addViolation();
    }
}
