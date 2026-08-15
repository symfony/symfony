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
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\DefaultGroupsDummy;
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
    }

    public function testNormalizeNoPropertyInGroup()
    {
        $normalizer = $this->getNormalizerForGroups();

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $this->assertEquals([], $normalizer->normalize($obj, null, ['groups' => ['notExist']]));
    }

    public function testNormalizeWithDefaultGroups()
    {
        $normalizer = $this->getNormalizerForGroups();

        $assertNormalizedProperties = function (array $expectedProperties, array $normalized): void {
            $actualProperties = array_keys($normalized);

            sort($expectedProperties);
            sort($actualProperties);

            $this->assertSame($expectedProperties, $actualProperties);
        };

        $assertNormalizedProperties(
            ['foo', 'fooBar', 'symfony', 'kevin', 'coopTilleuls'],
            $normalizer->normalize(new GroupDummy(), context: ['groups' => ['a']]),
        );

        $assertNormalizedProperties(
            ['bar', 'foo', 'fooBar', 'symfony', 'quux', 'default', 'className', 'kevin', 'coopTilleuls'],
            $normalizer->normalize(new GroupDummy()),
        );

        $assertNormalizedProperties(
            ['bar', 'foo', 'fooBar', 'symfony', 'quux', 'default', 'className', 'kevin', 'coopTilleuls'],
            $normalizer->normalize(new GroupDummy(), context: ['groups' => []]),
        );

        $assertNormalizedProperties(
            ['foo', 'bar', 'quux', 'fooBar', 'symfony', 'default', 'className', 'kevin', 'coopTilleuls'],
            $normalizer->normalize(new GroupDummy(), context: ['groups' => ['*']]),
        );

        $assertNormalizedProperties(
            ['default'],
            $normalizer->normalize(new GroupDummy(), context: ['groups' => ['Default']]),
        );

        $assertNormalizedProperties(
            ['className'],
            $normalizer->normalize(new GroupDummy(), context: ['groups' => ['GroupDummy']]),
        );

        $assertNormalizedProperties(
            ['foo', 'fooBar', 'symfony', 'kevin', 'coopTilleuls'],
            $normalizer->normalize(new GroupDummy(), context: [
                'enable_default_groups' => true,
                'groups' => ['a'],
            ]),
        );

        $assertNormalizedProperties(
            ['bar', 'foo', 'fooBar', 'symfony', 'quux', 'default', 'className', 'kevin', 'coopTilleuls'],
            $normalizer->normalize(new GroupDummy(), context: [
                'enable_default_groups' => true,
                'groups' => [],
            ]),
        );

        $assertNormalizedProperties(
            ['foo', 'bar', 'quux', 'fooBar', 'symfony', 'default', 'className', 'kevin', 'coopTilleuls'],
            $normalizer->normalize(new GroupDummy(), context: [
                'enable_default_groups' => true,
                'groups' => ['*'],
            ]),
        );

        $assertNormalizedProperties(
            ['default', 'className'],
            $normalizer->normalize(new GroupDummy(), context: [
                'enable_default_groups' => true,
                'groups' => ['Default'],
            ]),
        );

        $assertNormalizedProperties(
            ['default', 'className'],
            $normalizer->normalize(new GroupDummy(), context: [
                'enable_default_groups' => true,
                'groups' => ['GroupDummy'],
            ]),
        );
    }

    public function testNormalizeWithDefaultGroupsAndUngroupedProperty()
    {
        $normalizer = $this->getNormalizerForGroups();

        $assertNormalizedProperties = function (array $expectedProperties, array $normalized): void {
            $actualProperties = array_keys($normalized);

            sort($expectedProperties);
            sort($actualProperties);

            $this->assertSame($expectedProperties, $actualProperties);
        };

        // Ungrouped properties match Default and class-short-name groups when the flag is on.
        $assertNormalizedProperties(
            ['noGroup', 'defaultGroup', 'classGroup'],
            $normalizer->normalize(new DefaultGroupsDummy(), context: [
                'enable_default_groups' => true,
                'groups' => ['Default'],
            ]),
        );

        $assertNormalizedProperties(
            ['noGroup', 'defaultGroup', 'classGroup'],
            $normalizer->normalize(new DefaultGroupsDummy(), context: [
                'enable_default_groups' => true,
                'groups' => ['DefaultGroupsDummy'],
            ]),
        );

        // A custom group excludes ungrouped properties even when the flag is on.
        $assertNormalizedProperties(
            ['customGroup'],
            $normalizer->normalize(new DefaultGroupsDummy(), context: [
                'enable_default_groups' => true,
                'groups' => ['custom'],
            ]),
        );

        // groups=[] with flag on: $groupsHasBeenDefined must be captured before the
        // implicit defaultGroups merge, otherwise properties with non-Default groups
        // (like customGroup) would wrongly be filtered out.
        $assertNormalizedProperties(
            ['noGroup', 'customGroup', 'defaultGroup', 'classGroup'],
            $normalizer->normalize(new DefaultGroupsDummy(), context: [
                'enable_default_groups' => true,
                'groups' => [],
            ]),
        );
    }
}
