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
        $this->manager = new ArgumentResolverManager();
        $this->resolver1 = $this->getMock('Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface');
        $this->resolver2 = $this->getMock('Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface');
        $this->manager->addResolver($this->resolver1);
        $this->manager->addResolver($this->resolver2);

        $this->request = $this->getMock('Symfony\Component\HttpFoundation\Request');
    }

    public function testGetArgumentsFirstResolverAccepts()
    {
        $this->resolver1->expects($this->any())->method('accepts')->will($this->returnValue(true)); 
        $this->resolver1->expects($this->any())
            ->method('resolve')
            ->will($this->returnValue('resolved_value'));

        $controller = $this->getControllerWithOneArgument();

        $arguments = $this->manager->getArguments($this->request, $controller);
        $this->assertEquals(array('resolved_value'), $arguments);
    }

    public function testGetArgumentsSecondResolverAccepts()
    {
        $this->resolver1->expects($this->any())->method('accepts')->will($this->returnValue(false));
        $this->resolver2->expects($this->any())->method('accepts')->will($this->returnValue(true));
        $this->resolver2->expects($this->any())
            ->method('resolve')
            ->will($this->returnValue('resolved_value'));

        $controller = $this->getControllerWithOneArgument();

        $arguments = $this->manager->getArguments($this->request, $controller);
        $this->assertEquals(array('resolved_value'), $arguments);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testGetArgumentsFailsIfNoResolverAccepts()
    {
        $this->resolver1->expects($this->any())->method('accepts')->will($this->returnValue(false));
        $this->resolver2->expects($this->any())->method('accepts')->will($this->returnValue(false));

        $controller = $this->getControllerWithOneArgument();
        $this->manager->getArguments($this->request, $controller);
    }

    public function testGetArgumentResolvingMultipleArguments()
    {
        $this->resolver1->expects($this->any())
            ->method('accepts')
            ->will($this->onConsecutiveCalls(false, true, true));
        $this->resolver1->expects($this->any())
            ->method('resolve')
            ->will($this->onConsecutiveCalls('1st resolved by 1', '2nd resolved by 1'));

        $this->resolver2->expects($this->any())
            ->method('accepts')
            ->will($this->onConsecutiveCalls(true, false, true));
        $this->resolver2->expects($this->any())
            ->method('resolve')
            ->will($this->onConsecutiveCalls('1st resolved by 2', '2nd resolved by 2'));

        $controller = function ($a, $b, $c) { };

        $arguments = $this->manager->getArguments($this->request, $controller);
        $this->assertEquals(array('1st resolved by 2', '1st resolved by 1', '2nd resolved by 1'), $arguments);
    }

    public function testControllerWithOneOptionalArgumentWhichDoesNotMatch()
    {
        $this->resolver1->expects($this->any())->method('accepts')->will($this->returnValue(false));
        $this->resolver2->expects($this->any())->method('accepts')->will($this->returnValue(false));

        $arguments = $this->manager->getArguments($this->request, function ($a = 'default') { });
        $this->assertEquals(array('default'), $arguments);
    }

    public function testControllerWithOneOptionalArgumentWhichDoMatch()
    {
        $this->resolver1->expects($this->any())->method('accepts')->will($this->returnValue(true));
        $this->resolver1->expects($this->any())->method('resolve')->will($this->returnValue('resolved by 1'));
        $this->resolver2->expects($this->any())->method('accepts')->will($this->returnValue(false));

        $arguments = $this->manager->getArguments($this->request, function ($a = 'default') { });
        $this->assertEquals(array('resolved by 1'), $arguments);
    }

    protected function getControllerWithOneArgument()
    {
        return function ($a) { };
    }
}
