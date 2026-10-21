<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\EventListener\ConsoleLocaleListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[RequiresPhpExtension('intl')]
class ConsoleLocaleListenerTest extends TestCase
{
    private string $previousLocale;

    protected function setUp(): void
    {
        $this->previousLocale = \Locale::getDefault();
        \Locale::setDefault('de');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->previousLocale);
    }

    public function testCommandSetsTheDefaultLocale()
    {
        $listener = new ConsoleLocaleListener('fr', new RequestStack());

        $listener->onCommand($this->createCommandEvent(new Command('app:foo')));

        $this->assertSame('fr', \Locale::getDefault());
    }

    public function testOtherListenersCanSetAnotherLocale()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ConsoleLocaleListener('fr', new RequestStack()));
        $dispatcher->addListener(ConsoleEvents::COMMAND, static fn () => \Locale::setDefault('it'), 20);

        $dispatcher->dispatch($this->createCommandEvent(new Command('app:foo')), ConsoleEvents::COMMAND);

        $this->assertSame('it', \Locale::getDefault());
    }

    public function testNestedCommandKeepsTheCurrentLocale()
    {
        $listener = new ConsoleLocaleListener('fr', new RequestStack());
        $outer = new Command('app:outer');
        $inner = new Command('app:inner');

        $listener->onCommand($this->createCommandEvent($outer));
        \Locale::setDefault('de');
        $listener->onCommand($this->createCommandEvent($inner));

        $this->assertSame('de', \Locale::getDefault());

        $listener->onTerminate($this->createTerminateEvent($inner));
        $listener->onTerminate($this->createTerminateEvent($outer));
        $listener->onCommand($this->createCommandEvent($inner));

        $this->assertSame('fr', \Locale::getDefault());
    }

    public function testCommandRunDuringARequestKeepsTheCurrentLocale()
    {
        $listener = new ConsoleLocaleListener('fr', new RequestStack([Request::create('/')]));

        $listener->onCommand($this->createCommandEvent(new Command('app:foo')));

        $this->assertSame('de', \Locale::getDefault());
    }

    private function createCommandEvent(Command $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());
    }

    private function createTerminateEvent(Command $command): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), 0);
    }
}
