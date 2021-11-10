<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\EventDispatcherDebugCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class EventDispatcherDebugCommandCompletionTest extends TestCase
{
    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = $this->createCommandCompletionTester();

        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'event' => [[''], ['event', 'Listener']];
        yield 'event for other dispatcher' => [['--dispatcher=other_event_dispatcher', 'other'], ['other_event', 'OtherListener']];
        yield 'dispatcher' => [['--dispatcher='], ['event_dispatcher', 'other_event_dispatcher']];
        yield 'format' => [['--format='], ['txt', 'xml', 'json', 'md']];
    }

    private function createCommandCompletionTester(): CommandCompletionTester
    {
        $kernel = $this->createMock(KernelInterface::class);

        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->willReturnMap([]);

        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->willReturn([]);

        $container = new Container();

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $dispatcher = new EventDispatcher();
        $otherDispatcher = new EventDispatcher();

        $dispatcher->addListener('event', 'Listener');
        $otherDispatcher->addListener('other_event', 'OtherListener');

        $locator = $this->createLocator([
            'event_dispatcher' => $dispatcher,
            'other_event_dispatcher' => $otherDispatcher,
        ]);

        $command = new EventDispatcherDebugCommand($locator);

        $application = new Application($kernel);

        $application->add($command);

        return new CommandCompletionTester($application->find('debug:event-dispatcher'));
    }

    private function createLocator(array $linkers)
    {
        $locator = $this->createMock(ContainerBuilder::class);

        $locator->expects($this->any())
            ->method('has')
            ->willReturnCallback(function ($dispatcherServiceName) use ($linkers) {
                return isset($linkers[$dispatcherServiceName]);
            });

        $locator->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($dispatcherServiceName) use ($linkers) {
                return $linkers[$dispatcherServiceName];
            });

        $locator->expects($this->any())
            ->method('getServiceIds')
            ->willReturnCallback(function () use ($linkers) {
                return array_keys($linkers);
            });

        $locator->expects($this->any())
            ->method('getAliases')
            ->willReturnCallback(function () {
                return [];
            });

        return $locator;
    }
}
