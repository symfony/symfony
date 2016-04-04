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
    const PROFILE_BASIC_REGX = 'basic';
    const PROFILE_HTML5_REGX = 'html5';
    const PROFILE_RFC_ALLOW_WARNINGS = 'rfc';
    const PROFILE_RFC_DISALLOW_WARNINGS = 'rfc-no-warn';

    const INVALID_FORMAT_ERROR = 'bd79c0ab-ddba-46cc-a703-a7a4b08de310';
    const MX_CHECK_FAILED_ERROR = 'bf447c1c-0266-4e10-9c6c-573df282e413';
    const HOST_CHECK_FAILED_ERROR = '7da53a8b-56f3-4288-bb3e-ee9ede4ef9a1';

    protected static $errorNames = array(
        self::INVALID_FORMAT_ERROR => 'STRICT_CHECK_FAILED_ERROR',
        self::MX_CHECK_FAILED_ERROR => 'MX_CHECK_FAILED_ERROR',
        self::HOST_CHECK_FAILED_ERROR => 'HOST_CHECK_FAILED_ERROR',
    );

    public $message = 'This value is not a valid email address.';
    public $checkMX = false;
    public $checkHost = false;

    /**
     * Defines the validation profile/mode that will be used.
     * Options: basic, html5, rfc, rfc-no-warn.
     *
     * @var string
     */
    public $profile;

    /**
     * Specifies whether the rfc-no-warn (strict) or basic
     * validation profile should be used. This option is
     * now deprecated in favor of the 'profile' option.
     *
     * @deprecated
     *
     * @var bool
     */
    public $strict;
}
