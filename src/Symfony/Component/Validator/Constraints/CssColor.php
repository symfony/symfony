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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
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
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    /**
     * @var string[]
     */
    private static $validationModes = [
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

    public $message = 'This value is not a valid CSS color.';
    public $formats;

    /**
     * @param array|string $formats The types of CSS colors allowed (e.g. hexadecimal only, RGB and HSL only, etc.).
     */
    public function __construct($formats = [], string $message = null, array $groups = null, $payload = null, array $options = null)
    {
        $validationModesAsString = implode(', ', self::$validationModes);

        if (!$formats) {
            $options['value'] = self::$validationModes;
        } elseif (\is_array($formats) && \is_string(key($formats))) {
            $options = array_merge($formats, $options ?? []);
        } elseif (\is_array($formats)) {
            if ([] === array_intersect(self::$validationModes, $formats)) {
                throw new InvalidArgumentException(sprintf('The "formats" parameter value is not valid. It must contain one or more of the following values: "%s".', $validationModesAsString));
            }

            $options['value'] = $formats;
        } elseif (\is_string($formats)) {
            if (!\in_array($formats, self::$validationModes)) {
                throw new InvalidArgumentException(sprintf('The "formats" parameter value is not valid. It must contain one or more of the following values: "%s".', $validationModesAsString));
            }

            $options['value'] = [$formats];
        } else {
            throw new InvalidArgumentException('The "formats" parameter type is not valid. It should be a string or an array.');
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }

    public function getDefaultOption(): string
    {
        return 'formats';
    }

    public function getRequiredOptions(): array
    {
        return ['formats'];
    }
}
