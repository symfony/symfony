<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Instantiator\Instantiator;

/**
 * @author Baptite Leduc <baptiste.leduc@gmail.com>
 */
class StaticConstructorInstantiator extends Instantiator
{
    /**
     * {@inheritdoc}
     */
    protected function getConstructor(\ReflectionClass $reflectionClass): ?\ReflectionMethod
    {
        $class = $reflectionClass->getName();

        if (is_a($class, StaticConstructorDummy::class, true)) {
            return new \ReflectionMethod($class, 'create');
        }

        return parent::getConstructor($reflectionClass);
    }
}
