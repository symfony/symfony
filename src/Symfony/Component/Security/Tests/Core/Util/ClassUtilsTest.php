<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Core\Util
{
    use Symfony\Component\Security\Core\Util\ClassUtils;

    class ClassUtilsTest extends \PHPUnit_Framework_TestCase
    {
        public static function dataGetClass()
        {
            return array(
                array('stdClass', 'stdClass'),
                array('Symfony\Component\Security\Core\Util\ClassUtils', 'Symfony\Component\Security\Core\Util\ClassUtils'),
                array('MyProject\Proxies\__CG__\stdClass', 'stdClass'),
                array('MyProject\Proxies\__CG__\OtherProject\Proxies\__CG__\stdClass', 'stdClass'),
                array('MyProject\Proxies\__CG__\Symfony\Component\Security\Tests\Core\Util\ChildObject', 'Symfony\Component\Security\Tests\Core\Util\ChildObject'),
                array(new TestObject(), 'Symfony\Component\Security\Tests\Core\Util\TestObject'),
                array(new \Acme\DemoBundle\Proxy\__CG__\Symfony\Component\Security\Tests\Core\Util\TestObject(), 'Symfony\Component\Security\Tests\Core\Util\TestObject'),
            );
        }

        /**
         * @dataProvider dataGetClass
         */
        public function testGetRealClass($object, $expectedClassName)
        {
            $this->assertEquals($expectedClassName, ClassUtils::getRealClass($object));
        }
    }

    class TestObject
    {
    }
}

namespace Acme\DemoBundle\Proxy\__CG__\Symfony\Component\Security\Tests\Core\Util
{
    class TestObject extends \Symfony\Component\Security\Tests\Core\Util\TestObject
    {
    }
}
