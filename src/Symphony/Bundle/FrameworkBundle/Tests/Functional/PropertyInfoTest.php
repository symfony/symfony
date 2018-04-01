<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional;

use Symphony\Component\PropertyInfo\Type;

class PropertyInfoTest extends WebTestCase
{
    public function testPhpDocPriority()
    {
        static::bootKernel(array('test_case' => 'Serializer'));

        $this->assertEquals(array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_INT))), static::$container->get('property_info')->getTypes('Symphony\Bundle\FrameworkBundle\Tests\Functional\Dummy', 'codes'));
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
