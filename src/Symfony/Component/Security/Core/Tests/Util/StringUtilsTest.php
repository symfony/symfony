<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Util;

use Symfony\Component\Security\Core\Util\SecureRandom;
use Symfony\Component\Security\Core\Util\StringUtils;

/**
 * Data from PHP.net's hash_equals tests
 */
class StringUtilsTest extends \PHPUnit_Framework_TestCase
{
    protected $string;
    protected $utils;

    public function setUp()
    {
        $this->string = new StringUtils(new SecureRandom());
        $this->utils = new StringUtils(new SecureRandom());
    }

    public function dataProviderTrue()
    {
        return array(
            array('same', 'same'),
            array('', ''),
            array(123, 123),
            array(null, ''),
            array(null, null),
        );
    }

    public function dataProviderFalse()
    {
        return array(
            array('not1same', 'not2same'),
            array('short', 'longer'),
            array('longer', 'short'),
            array('', 'notempty'),
            array('notempty', ''),
            array(123, 'NaN'),
            array('NaN', 123),
            array(null, 123),
        );
    }

    public function dataProviderValidAlgos()
    {
        return array(
            array('sha1'),
            array('sha256'),
            array('sha512'),
            array('ripemd160'),
        );
    }

    public function dataProviderInvalidAlgos()
    {
        return array(
            array('pebkac'),
            array('pebcak'),
            array('invalid algo'),
            array(0),
            array(15.8),
            array(''),
            array(null),
        );
    }

    public function dataProviderValidKeySizes()
    {
        return array(
            array(10),
            array(1),
            array(52),
            array(25000),
            array(1024),
            array(PHP_INT_MAX),
            array('16'),
            array(15.0),
            array('17.0'),
        );
    }

    public function dataProviderInvalidKeySizes()
    {
        return array(
            array(-1),
            array(0),
            array(new \stdClass()),
            array(-150),
            array(''),
            array('-42'),
            array(null),
        );
    }

    /**
     * @dataProvider dataProviderTrue
     */
    public function testEqualsTrue($known, $user)
    {
        $this->assertTrue(StringUtils::equals($known, $user));
    }

    /**
     * @dataProvider dataProviderFalse
     */
    public function testEqualsFalse($known, $user)
    {
        $this->assertFalse(StringUtils::equals($known, $user));
    }

    /**
     * @dataProvider dataProviderTrue
     */
    public function testEqualsHashTrue($known, $user)
    {
        $this->assertTrue($this->string->equalsHash($known, $user));
    }

    /**
     * @dataProvider dataProviderFalse
     */
    public function testEqualsHashFalse($known, $user)
    {
        $this->assertFalse($this->string->equalsHash($known, $user));
    }

    /**
     * @dataProvider dataProviderInvalidAlgos
     * @expectedException \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    public function testInvalidHashAlgo($algo)
    {
        $this->utils->setAlgo($algo);
    }

    /**
     * @dataProvider dataProviderValidAlgos
     */
    public function testValidHashAlgo($algo)
    {
        $this->utils->setAlgo($algo);
    }

    /**
     * @dataProvider dataProviderValidKeySizes
     */
    public function testValidKeySize($size)
    {
        $this->utils->setKeySize($size);
        $this->assertTrue((int) $size === $this->utils->getKeySize());
    }

    /**
     * @dataProvider dataProviderInvalidKeySizes
     * @expectedException \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    public function testInvalidKeySize($size)
    {
        $this->utils->setKeySize($size);
    }
}
