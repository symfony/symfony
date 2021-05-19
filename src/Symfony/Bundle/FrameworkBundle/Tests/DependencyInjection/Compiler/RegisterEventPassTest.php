<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterEventPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterEventPassTest extends TestCase
{
    private const REGISTERED_EVENTS = [
        'console.command' => 'Symfony\Component\Console\Event\ConsoleCommandEvent',
        'console.terminate' => 'Symfony\Component\Console\Event\ConsoleTerminateEvent',
    ];

    private $container;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.events', self::REGISTERED_EVENTS);
        $this->container->register('app.not_event.service', 'Acme\Store\NotEvent\Service');
    }

    public function testRegisterEventsOnlyFromContainer(): void
    {
        $registerEventPass = new RegisterEventPass();
        $registerEventPass->process($this->container);

        $this->assertSame(self::REGISTERED_EVENTS, $this->container->getParameter('kernel.events'));
    }

    public function testRegisterEventsFromServicesAndContainer(): void
    {
        $this->container->register('app.event.order_placed', 'Acme\Store\Event\OrderPlacedEvent')
            ->addTag('kernel.event', ['template' => 'foo']);

        $registerEventPass = new RegisterEventPass();
        $registerEventPass->process($this->container);

        $this->assertSame(
            self::REGISTERED_EVENTS + ['app.event.order_placed' => 'Acme\Store\Event\OrderPlacedEvent'],
            $this->container->getParameter('kernel.events')
        );
    }
}
