<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

class PropertyInfoTest extends AbstractWebTestCase
{
    public function testPhpDocPriority()
    {
        static::bootKernel(['test_case' => 'Serializer']);

        $propertyInfo = static::getContainer()->get('property_info');

        if (!method_exists($propertyInfo, 'getType')) {
            $this->markTestSkipped();
        }

        $this->assertEquals(Type::list(Type::int()), $propertyInfo->getType(Dummy::class, 'codes'));
    }

    /**
     * @group legacy
     */
    public function testPhpDocPriorityLegacy()
    {
        static::bootKernel(['test_case' => 'Serializer']);

        $propertyInfo = static::getContainer()->get('property_info');

        if (!method_exists($propertyInfo, 'getTypes')) {
            $this->markTestSkipped();
        }

        $this->assertEquals([new LegacyType('array', false, null, true, new LegacyType('int'), new LegacyType('int'))], $propertyInfo->getTypes(Dummy::class, 'codes'));
    }
}

class Dummy
{
    /**
     * @param int[] $codes
     */
    public function setCodes(array $codes)
    {
    }
}
