<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Flag\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Flag\AbstractFlag;
use Symfony\Component\Flag\BinarizedFlag;
use Symfony\Component\Flag\Flag;
use Symfony\Component\Flag\HierarchicalFlag;
use Symfony\Component\Flag\Tests\Fixtures\Bar;

/**
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class AbstractFlagTest extends TestCase
{
    public function testSearchInGlobalSpace()
    {
        $flags = AbstractFlag::search(null, 'E_');

        $expects = array(
            array(E_ERROR, 'E_ERROR'),
            array(E_WARNING, 'E_WARNING'),
            array(E_NOTICE, 'E_NOTICE'),
            array(E_CORE_ERROR, 'E_CORE_ERROR'),
            array(E_CORE_WARNING, 'E_CORE_WARNING'),
            array(E_COMPILE_WARNING, 'E_COMPILE_ERROR'),
            array(E_USER_ERROR, 'E_USER_ERROR'),
            array(E_USER_WARNING, 'E_USER_WARNING'),
            array(E_STRICT, 'E_USER_NOTICE'),
            array(E_RECOVERABLE_ERROR, 'E_RECOVERABLE_ERROR'),
            array(E_DEPRECATED, 'E_DEPRECATED'),
            array(E_USER_DEPRECATED, 'E_USER_DEPRECATED'),
            array(E_ALL, 'E_ALL'),
        );

        foreach ($expects as $expect) {
            $this->assertArrayHasKey($expect[0], $flags);
            $this->assertContains($expect[1], $flags);
        }

        $this->assertArrayNotHasKey(PHP_VERSION, $flags);
        $this->assertNotContains('PHP_VERSION', $flags);
    }

    public function testSearchInClass()
    {
        $flags = AbstractFlag::search(Bar::class);

        foreach (Bar::getFlags() as $expect) {
            $this->assertArrayHasKey($expect[0], $flags);
            $this->assertContains($expect[1], $flags);
        }
    }

    public function testSearchInClassWithPrefix()
    {
        $flags = AbstractFlag::search(Bar::class, 'FLAG_');

        foreach (Bar::getPrefixedFlags() as $expected) {
            $this->assertArrayHasKey($expected[0], $flags);
            $this->assertContains($expected[1], $flags);
        }

        foreach (Bar::getNotPrefixedFlags() as $expected) {
            $this->assertArrayNotHasKey($expected[0], $flags);
            $this->assertNotContains($expected[1], $flags);
        }
    }

    /**
     * @expectedException \Symfony\Component\Flag\Exception\InvalidArgumentException
     * @expectedExceptionMessage A prefix must be setted if searching is in global space.
     */
    public function testSearchInGlobalSpaceWithoutPrefix()
    {
        AbstractFlag::search(null);
    }

    /**
     * @dataProvider provideCreate
     */
    public function testCreate($from, $prefix, $hierarchical, $expected)
    {
        $flag = AbstractFlag::create($from, $prefix, $hierarchical, 0);

        $this->assertInstanceOf($expected, $flag);
    }

    public function provideCreate()
    {
        return array(
            array(false, '', false, BinarizedFlag::class),

            array(null, 'E_', false, Flag::class),
            array(null, 'E_', true, HierarchicalFlag::class),

            array(Bar::class, '', false, BinarizedFlag::class),
            array(Bar::class, 'FLAG_', false, Flag::class),
            array(Bar::class, 'FLAG_', true, HierarchicalFlag::class),
        );
    }

    /**
     * @expectedException \Symfony\Component\Flag\Exception\InvalidArgumentException
     * @expectedExceptionMessage Potential no-integer flags must not be hierarchical.
     */
    public function testCreateHierarchicalStandalone()
    {
        AbstractFlag::create(false, '', true);
    }

    /**
     * @expectedException \Symfony\Component\Flag\Exception\InvalidArgumentException
     * @expectedExceptionMessage No-integer flags must not be hierarchical.
     */
    public function testCreateHierarchicalNoIntFlags()
    {
        AbstractFlag::create(Bar::class, '', true);
    }

    public function testSetAndGet()
    {
        $flag = $this->getMockBuilder(AbstractFlag::class)
            ->enableOriginalConstructor()
            ->setMethodsExcept(array('set', 'get'))
            ->getMock()
        ;

        $flag->set(1);
        $this->assertEquals(1, $flag->get());
    }
}
