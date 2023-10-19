<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;

class HelperSetTest extends TestCase
{
    public function testConstructor()
    {
        $mock_helper = $this->getGenericMockHelper('fake_helper');
        $helperset = new HelperSet(['fake_helper_alias' => $mock_helper]);

        $this->assertEquals($mock_helper, $helperset->get('fake_helper_alias'), '__construct sets given helper to helpers');
        $this->assertTrue($helperset->has('fake_helper_alias'), '__construct sets helper alias for given helper');
    }

    public function testSet()
    {
        $helperset = new HelperSet();
        $helperset->set($this->getGenericMockHelper('fake_helper', $helperset));
        $this->assertTrue($helperset->has('fake_helper'), '->set() adds helper to helpers');

        $helperset = new HelperSet();
        $helperset->set($this->getGenericMockHelper('fake_helper_01', $helperset));
        $helperset->set($this->getGenericMockHelper('fake_helper_02', $helperset));
        $this->assertTrue($helperset->has('fake_helper_01'), '->set() will set multiple helpers on consecutive calls');
        $this->assertTrue($helperset->has('fake_helper_02'), '->set() will set multiple helpers on consecutive calls');

        $helperset = new HelperSet();
        $helperset->set($this->getGenericMockHelper('fake_helper', $helperset), 'fake_helper_alias');
        $this->assertTrue($helperset->has('fake_helper'), '->set() adds helper alias when set');
        $this->assertTrue($helperset->has('fake_helper_alias'), '->set() adds helper alias when set');
    }

    public function testHas()
    {
        $helperset = new HelperSet(['fake_helper_alias' => $this->getGenericMockHelper('fake_helper')]);
        $this->assertTrue($helperset->has('fake_helper'), '->has() finds set helper');
        $this->assertTrue($helperset->has('fake_helper_alias'), '->has() finds set helper by alias');
    }

    public function testGet()
    {
        $helper_01 = $this->getGenericMockHelper('fake_helper_01');
        $helper_02 = $this->getGenericMockHelper('fake_helper_02');
        $helperset = new HelperSet(['fake_helper_01_alias' => $helper_01, 'fake_helper_02_alias' => $helper_02]);
        $this->assertEquals($helper_01, $helperset->get('fake_helper_01'), '->get() returns correct helper by name');
        $this->assertEquals($helper_01, $helperset->get('fake_helper_01_alias'), '->get() returns correct helper by alias');
        $this->assertEquals($helper_02, $helperset->get('fake_helper_02'), '->get() returns correct helper by name');
        $this->assertEquals($helper_02, $helperset->get('fake_helper_02_alias'), '->get() returns correct helper by alias');

        $helperset = new HelperSet();
        try {
            $helperset->get('foo');
            $this->fail('->get() throws InvalidArgumentException when helper not found');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->get() throws InvalidArgumentException when helper not found');
            $this->assertInstanceOf(ExceptionInterface::class, $e, '->get() throws domain specific exception when helper not found');
            $this->assertStringContainsString('The helper "foo" is not defined.', $e->getMessage(), '->get() throws InvalidArgumentException when helper not found');
        }
    }

    public function testIteration()
    {
        $helperset = new HelperSet();
        $helperset->set($this->getGenericMockHelper('fake_helper_01', $helperset));
        $helperset->set($this->getGenericMockHelper('fake_helper_02', $helperset));

        $helpers = ['fake_helper_01', 'fake_helper_02'];
        $i = 0;

        foreach ($helperset as $helper) {
            $this->assertEquals($helpers[$i++], $helper->getName());
        }
    }

    private function getGenericMockHelper($name, HelperSet $helperset = null)
    {
        $mock_helper = $this->createMock(HelperInterface::class);
        $mock_helper->expects($this->any())
            ->method('getName')
            ->willReturn($name);

        if ($helperset) {
            $mock_helper->expects($this->any())
                ->method('setHelperSet')
                ->with($this->equalTo($helperset));
        }

        return $mock_helper;
    }
}
