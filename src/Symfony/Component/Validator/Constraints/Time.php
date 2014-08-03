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
use Symfony\Component\Validator\Exception\InvalidConfigurationException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Time extends Constraint
{
    public $message = 'This value is not a valid time.';
    public $withMinutes = true;
    public $withSeconds = true;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (isset($options['withMinutes']) && !isset($options['withSeconds']) && $options['withMinutes'] == false) {
            $this->withSeconds = false;
        }

        if ($this->withSeconds && !$this->withMinutes) {
            throw new InvalidConfigurationException('You can not disable minutes if you have enabled seconds.');
        }
    }
}
