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
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy;

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
        $obj->setFooBar('fooBar');
        $obj->setSymfony('symfony');
        $obj->setKevin('kevin');
        $obj->setCoopTilleuls('coopTilleuls');

        $this->assertEquals([
            'bar' => 'bar',
        ], $normalizer->normalize($obj, null, ['groups' => ['c']]));

        $this->assertEquals([
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
            'kevin' => 'kevin',
            'coopTilleuls' => 'coopTilleuls',
        ], $normalizer->normalize($obj, null, ['groups' => ['a', 'c']]));
    }

    public function testGroupsDenormalize()
    {
        $normalizer = $this->getDenormalizerForGroups();

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $data = ['foo' => 'foo', 'bar' => 'bar'];

        $normalized = $normalizer->denormalize(
            $data,
            GroupDummy::class,
            null,
            ['groups' => ['a']]
        );
        $this->assertEquals($obj, $normalized);

        $obj->setBar('bar');

        $normalized = $normalizer->denormalize(
            $data,
            GroupDummy::class,
            null,
            ['groups' => ['a', 'b']]
        );
        $this->assertEquals($obj, $normalized);
    }

    public function testNormalizeNoPropertyInGroup()
    {
        $normalizer = $this->getNormalizerForGroups();

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $this->assertEquals([], $normalizer->normalize($obj, null, ['groups' => ['notExist']]));
    }
}
