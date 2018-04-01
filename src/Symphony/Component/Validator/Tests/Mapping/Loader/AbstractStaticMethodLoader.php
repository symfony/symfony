<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Mapping\Loader;

use Symphony\Component\Validator\Mapping\ClassMetadata;

abstract class AbstractStaticMethodLoader
{
    abstract public static function loadMetadata(ClassMetadata $metadata);
}
