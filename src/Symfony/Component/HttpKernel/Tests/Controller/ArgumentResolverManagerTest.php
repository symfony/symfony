<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller;

use Symfony\Component\HttpKernel\Controller\ArgumentResolverManager;

class ArgumentResolverManagerTest extends \PHPUnit_Framework_TestCase
{
    protected $manager;
    protected $resolver1;
    protected $resolver2;
    protected $request;

    public function setUp()
    {
        $this->resolver1 = $this->getMock('Symfony\Component\HttpKernel\Controller\ArgumentResolver\ArgumentResolverInterface');
        $this->resolver2 = $this->getMock('Symfony\Component\HttpKernel\Controller\ArgumentResolver\ArgumentResolverInterface');

        $this->manager = new ArgumentResolverManager(array($this->resolver1, $this->resolver2));

        $this->request = $this->getMock('Symfony\Component\HttpFoundation\Request');
    }

    public function testGetArgumentsWithoutControllerParameters()
    {
        $this->assertArguments(array(), function () { });
    }

    public function testGetArgumentsFirstResolverAccepts()
    {
        $this->promiseResolverToMatch($this->resolver1, 'resolved_value');

        $this->assertArguments(array('resolved_value'), $this->getControllerWithOneParameter());
    }

    public function testGetArgumentsSecondResolverAccepts()
    {
        $this->promiseResolverToNotMatch($this->resolver1);
        $this->promiseResolverToMatch($this->resolver2, 'resolved_value');

        $this->assertArguments(array('resolved_value'), $this->getControllerWithOneParameter());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testGetArgumentsFailsIfNoResolverAccepts()
    {
        $this->promiseResolverToNotMatch($this->resolver1);
        $this->promiseResolverToNotMatch($this->resolver2);

        $this->manager->getArguments($this->request, $this->getControllerWithOneParameter());
    }

    public function testGetArgumentResolvingMultipleParameters()
    {
        $this->resolver1->expects($this->any())
            ->method('supports')
            ->will($this->onConsecutiveCalls(false, true, true));
        $this->resolver1->expects($this->any())
            ->method('resolve')
            ->will($this->onConsecutiveCalls('1st resolved by 1', '2nd resolved by 1'));

        $this->resolver2->expects($this->any())
            ->method('supports')
            ->will($this->onConsecutiveCalls(true, false, true));
        $this->resolver2->expects($this->any())
            ->method('resolve')
            ->will($this->onConsecutiveCalls('1st resolved by 2', '2nd resolved by 2'));

        $this->assertArguments(
            array('1st resolved by 2', '1st resolved by 1', '2nd resolved by 1'),
            function ($a, $b, $c) { }
        );
    }

    public function testControllerWithOneOptionalParameterWhichDoesNotMatch()
    {
        $this->promiseResolverToNotMatch($this->resolver1);
        $this->promiseResolverToNotMatch($this->resolver2);

        $this->assertArguments(array('default'), function ($a = 'default') { });
    }

    public function testControllerWithOneOptionalParameterWhichDoesMatch()
    {
        $this->promiseResolverToMatch($this->resolver1, 'resolved by 1');
        $this->promiseResolverToNotMatch($this->resolver2);

        $this->assertArguments(array('resolved by 1'), function ($a = 'default') { });
    }

    public function testControllerWithOneParameterWithNullDefault()
    {
        $this->promiseResolverToNotMatch($this->resolver1);
        $this->promiseResolverToNotMatch($this->resolver2);

        $this->assertArguments(array(null), function ($a = null) { });
    }

    private function assertArguments(array $expected, $controller)
    {
        $this->assertEquals($expected, $this->manager->getArguments($this->request, $controller));
    }

    private function promiseResolverToMatch($resolver, $return)
    {
        $resolver->expects($this->any())->method('supports')->will($this->returnValue(true));
        $resolver->expects($this->any())->method('resolve')->will($this->returnValue($return));
    }

    private function promiseResolverToNotMatch($resolver)
    {
        $resolver->expects($this->any())->method('supports')->will($this->returnValue(false));
    }

    private function getControllerWithOneParameter()
    {
        return function ($a) { };
    }
}
