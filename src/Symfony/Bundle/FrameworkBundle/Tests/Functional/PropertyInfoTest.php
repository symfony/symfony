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

use Symfony\Component\PropertyInfo\Type;

class PropertyInfoTest extends WebTestCase
{
    public function testPhpDocPriority()
    {
        static::bootKernel(array('test_case' => 'Serializer'));
        $container = static::$kernel->getContainer();

        $this->assertEquals(array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_INT))), $container->get('test.property_info')->getTypes('Symfony\Bundle\FrameworkBundle\Tests\Functional\Dummy', 'codes'));
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
