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
class Locale extends Constraint
{
    const NO_SUCH_LOCALE_ERROR = 'a0af4293-1f1a-4a1c-a328-979cba6182a2';

    protected static $errorNames = array(
        self::NO_SUCH_LOCALE_ERROR => 'NO_SUCH_LOCALE_ERROR',
    );

    public $message = 'This value is not a valid locale.';
    public $canonicalize = false;

    public function __construct($options = null)
    {
        if (!($options['canonicalize'] ?? false)) {
            @trigger_error('The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.', E_USER_DEPRECATED);
        }

        parent::__construct($options);
    }
}
