<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummy;

/**
 * Test AbstractNormalizer::GROUPS.
 */
trait GroupsTestTrait
{
    abstract protected function getNormalizerForGroups(): NormalizerInterface;

    abstract protected function getDenormalizerForGroups(): DenormalizerInterface;

    public function testGroupsNormalize()
    {
        $normalizer = $this->getNormalizerForGroups();

        $obj = new GroupDummy();
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $obj->setQuux('quux');
        $obj->setFooBar('fooBar');
        $obj->setSymfony('symfony');
        $obj->setKevin('kevin');
        $obj->setCoopTilleuls('coopTilleuls');
        $obj->setDefault('default');
        $obj->setClassName('className');

        $this->assertEquals([
            'bar' => 'bar',
            'default' => 'default',
            'className' => 'className',
        ], $normalizer->normalize($obj, null, ['groups' => ['c']]));

        $this->assertEquals([
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
            'kevin' => 'kevin',
            'coopTilleuls' => 'coopTilleuls',
            'default' => 'default',
            'className' => 'className',
        ], $normalizer->normalize($obj, null, ['groups' => ['a', 'c']]));

        $this->assertEquals([
            'default' => 'default',
            'className' => 'className',
        ], $normalizer->normalize($obj, null, ['groups' => ['unknown']]));

        $this->assertEquals([
            'quux' => 'quux',
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
            'kevin' => 'kevin',
            'coopTilleuls' => 'coopTilleuls',
            'default' => 'default',
            'className' => 'className',
        ], $normalizer->normalize($obj));
    }

    public function testGroupsDenormalize()
    {
        $normalizer = $this->getDenormalizerForGroups();

        $obj = new GroupDummy();
        $obj->setDefault('default');
        $obj->setClassName('className');

        $data = [
            'foo' => 'foo',
            'bar' => 'bar',
            'quux' => 'quux',
            'default' => 'default',
            'className' => 'className',
        ];

        $denormalized = $normalizer->denormalize(
            $data,
            GroupDummy::class,
            null,
            ['groups' => ['unknown']]
        );
        $this->assertEquals($obj, $denormalized);

        $obj->setFoo('foo');

        $denormalized = $normalizer->denormalize(
            $data,
            GroupDummy::class,
            null,
            ['groups' => ['a']]
        );
        $this->assertEquals($obj, $denormalized);

        $obj->setBar('bar');

        $denormalized = $normalizer->denormalize(
            $data,
            GroupDummy::class,
            null,
            ['groups' => ['a', 'b']]
        );
        $this->assertEquals($obj, $denormalized);

        $obj->setQuux('quux');

        $denormalized = $normalizer->denormalize($data, GroupDummy::class);
        $this->assertEquals($obj, $denormalized);
    }

    public function testNormalizeNoPropertyInGroup()
    {
        $normalizer = $this->getNormalizerForGroups();

        $obj = new GroupDummy();
        $obj->setFoo('foo');
        $obj->setDefault('default');
        $obj->setClassName('className');

        $this->assertEquals([
            'default' => 'default',
            'className' => 'className',
        ], $normalizer->normalize($obj, null, ['groups' => ['notExist']]));
    }
}
