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
use Symfony\Component\Validator\Exception\LogicException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Email extends Constraint
{
    public const VALIDATION_MODE_HTML5 = 'html5';
    public const VALIDATION_MODE_STRICT = 'strict';
    public const VALIDATION_MODE_LOOSE = 'loose';

    const INVALID_FORMAT_ERROR = 'bd79c0ab-ddba-46cc-a703-a7a4b08de310';

    /**
     * @deprecated since Symfony 4.2.
     */
    const MX_CHECK_FAILED_ERROR = 'bf447c1c-0266-4e10-9c6c-573df282e413';

    /**
     * @deprecated since Symfony 4.2.
     */
    const HOST_CHECK_FAILED_ERROR = '7da53a8b-56f3-4288-bb3e-ee9ede4ef9a1';

    protected static $errorNames = [
        self::INVALID_FORMAT_ERROR => 'STRICT_CHECK_FAILED_ERROR',
        self::MX_CHECK_FAILED_ERROR => 'MX_CHECK_FAILED_ERROR',
        self::HOST_CHECK_FAILED_ERROR => 'HOST_CHECK_FAILED_ERROR',
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

    /**
     * @deprecated since Symfony 4.2.
     */
    public $checkMX = false;

    /**
     * @deprecated since Symfony 4.2.
     */
    public $checkHost = false;

    /**
     * @deprecated since Symfony 4.1, set mode to "strict" instead.
     */
    public $strict;
    public $mode;

    public function __construct($options = null)
    {
        if (\is_array($options) && \array_key_exists('strict', $options)) {
            @trigger_error(sprintf('The "strict" property is deprecated since Symfony 4.1. Use "mode"=>"%s" instead.', self::VALIDATION_MODE_STRICT), E_USER_DEPRECATED);
        }

        if (\is_array($options) && \array_key_exists('checkMX', $options)) {
            @trigger_error('The "checkMX" option is deprecated since Symfony 4.2.', E_USER_DEPRECATED);
        }

        if (\is_array($options) && \array_key_exists('checkHost', $options)) {
            @trigger_error('The "checkHost" option is deprecated since Symfony 4.2.', E_USER_DEPRECATED);
        }

        if (\is_array($options) && \array_key_exists('mode', $options) && !\in_array($options['mode'], self::$validationModes, true)) {
            throw new \InvalidArgumentException('The "mode" parameter value is not valid.');
        }

        parent::__construct($options);

        if ((self::VALIDATION_MODE_STRICT === $this->mode || true === $this->strict) && !class_exists(StrictEmailValidator::class)) {
            // throw new LogicException(sprintf('The "egulias/email-validator" component is required to use the "%s" constraint in strict mode.', __CLASS__));
            @trigger_error(sprintf('Using the "%s" constraint in strict mode without the "egulias/email-validator" component installed is deprecated since Symfony 4.2.', __CLASS__), E_USER_DEPRECATED);
        }
    }
}
