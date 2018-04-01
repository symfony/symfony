<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Fixtures;

use Symphony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CallbackClass
{
    public static function callback($object, ExecutionContextInterface $context)
    {
    }
}
