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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Length extends Constraint
{
    public const TOO_SHORT_ERROR = '9ff3fdc4-b214-49db-8718-39c315e33d45';
    public const TOO_LONG_ERROR = 'd94b19cc-114f-4f44-9cc4-4138e80a87b9';
    public const NOT_EQUAL_LENGTH_ERROR = '4b6f5c76-22b4-409d-af16-fbe823ba9332';
    public const INVALID_CHARACTERS_ERROR = '35e6a710-aa2e-4719-b58e-24b35749b767';

    protected const ERROR_NAMES = [
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::NOT_EQUAL_LENGTH_ERROR => 'NOT_EQUAL_LENGTH_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
    ];

    public const COUNT_BYTES = 'bytes';
    public const COUNT_CODEPOINTS = 'codepoints';
    public const COUNT_GRAPHEMES = 'graphemes';

    private const VALID_COUNT_UNITS = [
        self::COUNT_BYTES,
        self::COUNT_CODEPOINTS,
        self::COUNT_GRAPHEMES,
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $maxMessage = 'This value is too long. It should have {{ limit }} character or less.|This value is too long. It should have {{ limit }} characters or less.';
    public $minMessage = 'This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.';
    public $exactMessage = 'This value should have exactly {{ limit }} character.|This value should have exactly {{ limit }} characters.';
    public $charsetMessage = 'This value does not match the expected {{ charset }} charset.';
    public $max;
    public $min;
    public $charset = 'UTF-8';
    /** @var callable|null */
    public $normalizer;
    /** @var self::COUNT_* */
    public string $countUnit = self::COUNT_CODEPOINTS;

    /**
     * @param self::COUNT_*|null $countUnit
     */
    public function __construct(
        int|array $exactly = null,
        int $min = null,
        int $max = null,
        string $charset = null,
        callable $normalizer = null,
        string $countUnit = null,
        string $exactMessage = null,
        string $minMessage = null,
        string $maxMessage = null,
        string $charsetMessage = null,
        array $groups = null,
        mixed $payload = null,
        array $options = []
    ) {
        if (\is_array($exactly)) {
            $options = array_merge($exactly, $options);
            $exactly = $options['value'] ?? null;
        }

        $min ??= $options['min'] ?? null;
        $max ??= $options['max'] ?? null;

        unset($options['value'], $options['min'], $options['max']);

        if (null !== $exactly && null === $min && null === $max) {
            $min = $max = $exactly;
        }

        parent::__construct($options, $groups, $payload);

        $this->min = $min;
        $this->max = $max;
        $this->charset = $charset ?? $this->charset;
        $this->normalizer = $normalizer ?? $this->normalizer;
        $this->countUnit = $countUnit ?? $this->countUnit;
        $this->exactMessage = $exactMessage ?? $this->exactMessage;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->charsetMessage = $charsetMessage ?? $this->charsetMessage;

        if (null === $this->min && null === $this->max) {
            throw new MissingOptionsException(sprintf('Either option "min" or "max" must be given for constraint "%s".', __CLASS__), ['min', 'max']);
        }

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }

        if (!\in_array($this->countUnit, self::VALID_COUNT_UNITS)) {
            throw new InvalidArgumentException(sprintf('The "countUnit" option must be one of the "%s"::COUNT_* constants ("%s" given).', __CLASS__, $this->countUnit));
        }
    }
}
