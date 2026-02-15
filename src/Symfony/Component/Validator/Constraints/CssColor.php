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
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Validates that a value is a valid CSS color.
 *
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CssColor extends Constraint
{
    public const HEX_LONG = 'hex_long';
    public const HEX_LONG_WITH_ALPHA = 'hex_long_with_alpha';
    public const HEX_SHORT = 'hex_short';
    public const HEX_SHORT_WITH_ALPHA = 'hex_short_with_alpha';
    public const BASIC_NAMED_COLORS = 'basic_named_colors';
    public const EXTENDED_NAMED_COLORS = 'extended_named_colors';
    public const SYSTEM_COLORS = 'system_colors';
    public const KEYWORDS = 'keywords';
    public const RGB = 'rgb';
    public const RGBA = 'rgba';
    public const HSL = 'hsl';
    public const HSLA = 'hsla';
    public const INVALID_FORMAT_ERROR = '454ab47b-aacf-4059-8f26-184b2dc9d48d';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
    ];

    /**
     * @var string[]
     */
    private static array $validationModes = [
        self::HEX_LONG,
        self::HEX_LONG_WITH_ALPHA,
        self::HEX_SHORT,
        self::HEX_SHORT_WITH_ALPHA,
        self::BASIC_NAMED_COLORS,
        self::EXTENDED_NAMED_COLORS,
        self::SYSTEM_COLORS,
        self::KEYWORDS,
        self::RGB,
        self::RGBA,
        self::HSL,
        self::HSLA,
    ];

    public string $message = 'This value is not a valid CSS color.';
    public array|string $formats;

    /**
     * @param non-empty-string[]|non-empty-string $formats The types of CSS colors allowed ({@see https://symfony.com/doc/current/reference/constraints/CssColor.html#formats})
     * @param string[]|null                       $groups
     */
    public function __construct(array|string $formats = [], ?string $message = null, ?array $groups = null, $payload = null)
    {
        $validationModesAsString = implode(', ', self::$validationModes);

        if (!$formats) {
            $formats = self::$validationModes;
        } elseif (\is_array($formats)) {
            if ([] === array_intersect(self::$validationModes, $formats)) {
                throw new InvalidArgumentException(\sprintf('The "formats" parameter value is not valid. It must contain one or more of the following values: "%s".', $validationModesAsString));
            }
        } elseif (\is_string($formats)) {
            if (!\in_array($formats, self::$validationModes, true)) {
                throw new InvalidArgumentException(\sprintf('The "formats" parameter value is not valid. It must contain one or more of the following values: "%s".', $validationModesAsString));
            }

            $formats = [$formats];
        } else {
            throw new InvalidArgumentException('The "formats" parameter type is not valid. It should be a string or an array.');
        }

        parent::__construct(null, $groups, $payload);

        $this->formats = $formats ?? $this->formats;
        $this->message = $message ?? $this->message;
    }
}
