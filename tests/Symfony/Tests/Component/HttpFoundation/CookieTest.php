<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * CookieTest
 *
 * @author John Kary <john@johnkary.net>
 */
class CookieTest extends \PHPUnit_Framework_TestCase
{
    public function invalidNames()
    {
        return array(
            array(''),
            array(",MyName"),
            array(";MyName"),
            array(" MyName"),
            array("\tMyName"),
            array("\rMyName"),
            array("\nMyName"),
            array("\013MyName"),
            array("\014MyName"),
        );
    }

    public function invalidValues()
    {
        return array(
            array(",MyValue"),
            array(";MyValue"),
            array(" MyValue"),
            array("\tMyValue"),
            array("\rMyValue"),
            array("\nMyValue"),
            array("\013MyValue"),
            array("\014MyValue"),
        );
    }

    /**
     * @dataProvider invalidNames
     * @expectedException InvalidArgumentException
     * @covers Symfony\Component\HttpFoundation\Cookie::__construct
     */
    public function testInstantiationThrowsExceptionIfCookieNameContainsInvalidCharacters($name)
    {
        new Cookie($name);
    }

    /**
     * @dataProvider invalidValues
     * @expectedException InvalidArgumentException
     * @covers Symfony\Component\HttpFoundation\Cookie::__construct
     */
    public function testInstantiationThrowsExceptionIfCookieValueContainsInvalidCharacters($value)
    {
        new Cookie('MyCookie', $value);
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Cookie::getValue
     */
    public function testGetValue()
    {
        $value = 'MyValue';
        $cookie = new Cookie('MyCookie', $value);

        $this->assertSame($value, $cookie->getValue(), '->getValue() returns the proper value');
    }
}
