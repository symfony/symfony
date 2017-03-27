<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Mapping;

use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TestClassMetadataFactory
{
    public static function createClassMetadata()
    {
        $expected = new ClassMetadata('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy');

        $expected->getReflectionClass();

        $foo = new PropertyMetadata('foo');
        $foo->setGetter('getter1');
        $foo->setSetter('setter1');
        $foo->setAdder('adder1');
        $foo->setRemover('remover1');
        $expected->addPropertyMetadata($foo);

        $bar = new PropertyMetadata('bar');
        $bar->setGetter('getter2');
        $expected->addPropertyMetadata($bar);

        $test = new PropertyMetadata('test');
        $test->setGetter('testChild');
        $expected->addPropertyMetadata($test);

        return $expected;
    }

    public static function createXMLClassMetadata()
    {
        $expected = new ClassMetadata('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy');

        $foo = new PropertyMetadata('foo');
        $foo->setGetter('getter1');
        $foo->setSetter('setter1');
        $foo->setAdder('adder1');
        $foo->setRemover('remover1');
        $expected->addPropertyMetadata($foo);

        $bar = new PropertyMetadata('bar');
        $bar->setGetter('getter2');
        $expected->addPropertyMetadata($bar);

        return $expected;
    }
}
