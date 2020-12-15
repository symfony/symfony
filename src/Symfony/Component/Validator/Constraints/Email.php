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

use Egulias\EmailValidator\EmailValidator as StrictEmailValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Email extends Constraint
{
    public const VALIDATION_MODE_HTML5 = 'html5';
    public const VALIDATION_MODE_STRICT = 'strict';
    public const VALIDATION_MODE_LOOSE = 'loose';

    public const INVALID_FORMAT_ERROR = 'bd79c0ab-ddba-46cc-a703-a7a4b08de310';

    protected static $errorNames = [
        self::INVALID_FORMAT_ERROR => 'STRICT_CHECK_FAILED_ERROR',
    ];

    /**
     * @var string[]
     *
     * @internal
     */
    public static $validationModes = [
        self::VALIDATION_MODE_HTML5,
        self::VALIDATION_MODE_STRICT,
        self::VALIDATION_MODE_LOOSE,
    ];

    public $message = 'This value is not a valid email address.';
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

        if (self::VALIDATION_MODE_STRICT === $this->mode && !class_exists(StrictEmailValidator::class)) {
            throw new LogicException(sprintf('The "egulias/email-validator" component is required to use the "%s" constraint in strict mode.', __CLASS__));
        }

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
