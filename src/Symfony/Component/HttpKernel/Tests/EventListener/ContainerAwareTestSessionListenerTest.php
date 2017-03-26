<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\EventListener\ContainerAwareTestSessionListener;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ContainerAwareTestSessionListenerTest extends TestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var ContainerAwareTestSessionListener
     */
    private $listener;

    protected function setUp()
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->session = $this->getMockBuilder(SessionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->listener = new ContainerAwareTestSessionListener($this->container);
    }

    public function testShouldGetSessionService()
    {
        $this->containerHavingSession();

        $this->assertSame($this->session, $this->getSession());
    }

    public function testShouldGetSessionNullWhenServiceIsNotDefined()
    {
        $this->containerNotHavingSession();

        $this->assertNull($this->getSession());
    }

    private function getSession()
    {
        $method = (new \ReflectionClass($this->listener))
             ->getMethod('getSession');
        $method->setAccessible(true);

        return $method->invoke($this->listener);
    }

    private function containerHavingSession()
    {
        $this->container->expects($this->any())
            ->method('has')
            ->with($this->equalTo('session'))
            ->will($this->returnValue(true));

        $this->container->expects($this->any())
            ->method('get')
            ->with($this->equalTo('session'))
            ->will($this->returnValue($this->session));
    }

    private function containerNotHavingSession()
    {
        $this->container->expects($this->any())
            ->method('has')
            ->with($this->equalTo('session'))
            ->will($this->returnValue(false));

        $this->container->expects($this->never())
            ->method('get')
            ->with($this->equalTo('session'));
    }
}
