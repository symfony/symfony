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

use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummy;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TestClassMetadataFactory
{
    public static function createClassMetadata(string $namespace, bool $withParent = false, bool $withInterface = false): ClassMetadata
    {
        $expected = new ClassMetadata($namespace.'\GroupDummy');

        $foo = new AttributeMetadata('foo');
        $foo->addGroup('a');
        $expected->addAttributeMetadata($foo);

        $bar = new AttributeMetadata('bar');
        $bar->addGroup('b');
        $bar->addGroup('c');
        $bar->addGroup('name_converter');
        $expected->addAttributeMetadata($bar);

        $quux = new AttributeMetadata('quux');
        $quux->addGroup('d');
        $expected->addAttributeMetadata($quux);

        $fooBar = new AttributeMetadata('fooBar');
        $fooBar->addGroup('a');
        $fooBar->addGroup('b');
        $fooBar->addGroup('name_converter');
        $expected->addAttributeMetadata($fooBar);

        $symfony = new AttributeMetadata('symfony');
        $expected->addAttributeMetadata($symfony);

        if ($withParent) {
            $kevin = new AttributeMetadata('kevin');
            $kevin->addGroup('a');
            $expected->addAttributeMetadata($kevin);

            $coopTilleuls = new AttributeMetadata('coopTilleuls');
            $coopTilleuls->addGroup('a');
            $coopTilleuls->addGroup('b');
            $expected->addAttributeMetadata($coopTilleuls);
        }

        if ($withInterface) {
            $symfony->addGroup('a');
            $symfony->addGroup('name_converter');
        }

        $default = new AttributeMetadata('default');
        $default->addGroup('Default');
        $expected->addAttributeMetadata($default);

        $className = new AttributeMetadata('className');
        $className->addGroup('GroupDummy');
        $expected->addAttributeMetadata($className);

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();

        return $expected;
    }

    public static function createXmlCLassMetadata(): ClassMetadata
    {
        $expected = new ClassMetadata(GroupDummy::class);

        $foo = new AttributeMetadata('foo');
        $foo->addGroup('group1');
        $foo->addGroup('group2');
        $expected->addAttributeMetadata($foo);

        $bar = new AttributeMetadata('bar');
        $bar->addGroup('group2');
        $expected->addAttributeMetadata($bar);

        return $expected;
    }
}
