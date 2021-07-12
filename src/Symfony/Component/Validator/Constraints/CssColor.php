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
    public const VALIDATION_MODE_HEX_LONG = 'hex_long';
    public const VALIDATION_MODE_HEX_SHORT = 'hex_short';
    public const VALIDATION_MODE_NAMED_COLORS = 'named_colors';
    public const INVALID_FORMAT_ERROR = '454ab47b-aacf-4059-8f26-184b2dc9d48d';

    protected static $errorNames = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
    ];

    /**
     * @var string[]
     */
    private static $validationModes = [
        self::VALIDATION_MODE_HEX_LONG,
        self::VALIDATION_MODE_HEX_SHORT,
        self::VALIDATION_MODE_NAMED_COLORS,
    ];

    public $message = 'This value is not a valid hexadecimal color.';
    public $mode;
    public $normalizer;

    public function __construct(
        array $options = null,
        string $message = null,
        string $mode = null,
        callable $normalizer = null,
        array $groups = null,
        $payload = null
    ) {
        if (\is_array($options) && \array_key_exists('mode', $options) && !\in_array($options['mode'], self::$validationModes, true)) {
            throw new InvalidArgumentException('The "mode" parameter value is not valid.');
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->mode = $mode ?? $this->mode;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
