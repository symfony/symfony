<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping;

use Symfony\Component\Serializer\Mapping\ClassMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TestClassMetadataFactory
{
    public static function createClassMetadata($withParent = false, $withInterface = false)
    {
        $expected = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\GroupDummy');

        if ($withParent) {
            $expected->addAttributeGroup('kevin', 'a');
            $expected->addAttributeGroup('coopTilleuls', 'a');
            $expected->addAttributeGroup('coopTilleuls', 'b');
        }

        if ($withInterface) {
            $expected->addAttributeGroup('symfony', 'a');
        }

        $expected->addAttributeGroup('foo', 'a');
        $expected->addAttributeGroup('bar', 'b');
        $expected->addAttributeGroup('bar', 'c');
        $expected->addAttributeGroup('fooBar', 'a');
        $expected->addAttributeGroup('fooBar', 'b');

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();

        return $expected;
    }

    public static function createXmlCLassMetadata()
    {
        $expected = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\GroupDummy');
        $expected->addAttributeGroup('foo', 'group1');
        $expected->addAttributeGroup('foo', 'group2');
        $expected->addAttributeGroup('bar', 'group2');

        return $expected;
    }
}
