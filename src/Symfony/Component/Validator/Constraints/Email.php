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
    const MX_CHECK_FAILED_ERROR = 'bf447c1c-0266-4e10-9c6c-573df282e413';
    const HOST_CHECK_FAILED_ERROR = '7da53a8b-56f3-4288-bb3e-ee9ede4ef9a1';

    protected static $errorNames = array(
        self::INVALID_FORMAT_ERROR => 'STRICT_CHECK_FAILED_ERROR',
        self::MX_CHECK_FAILED_ERROR => 'MX_CHECK_FAILED_ERROR',
        self::HOST_CHECK_FAILED_ERROR => 'HOST_CHECK_FAILED_ERROR',
    );

    /**
     * @var string[]
     *
     * @internal
     */
    public static $validationModes = array(
        self::VALIDATION_MODE_HTML5,
        self::VALIDATION_MODE_STRICT,
        self::VALIDATION_MODE_LOOSE,
    );

    public $message = 'This value is not a valid email address.';
    public $checkMX = false;
    public $checkHost = false;

    /**
     * @deprecated since Symfony 4.1. Set mode to "strict" instead.
     */
    public $strict;
    public $mode;

    public function __construct($options = null)
    {
        if (is_array($options) && array_key_exists('strict', $options)) {
            @trigger_error(sprintf('The "strict" property is deprecated since Symfony 4.1. Use "mode"=>"%s" instead.', self::VALIDATION_MODE_STRICT), E_USER_DEPRECATED);
        }

        if (is_array($options) && array_key_exists('mode', $options) && !in_array($options['mode'], self::$validationModes, true)) {
            throw new \InvalidArgumentException('The "mode" parameter value is not valid.');
        }

        parent::__construct($options);
    }
}
