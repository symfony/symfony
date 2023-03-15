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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Event\MessageEvent;

class EventDispatcherDebugCommandTest extends TestCase
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

    public static function provideCompletionSuggestions()
    {
        yield 'event' => [[''], [MessageEvent::class, 'console.command']];
        yield 'event for other dispatcher' => [['--dispatcher', 'other_event_dispatcher', ''], ['other_event', 'App\OtherEvent']];
        yield 'dispatcher' => [['--dispatcher='], ['event_dispatcher', 'other_event_dispatcher']];
        yield 'format' => [['--format='], ['txt', 'xml', 'json', 'md']];
    }

    private function createCommandCompletionTester(): CommandCompletionTester
    {
        $dispatchers = new ServiceLocator([
            'event_dispatcher' => function () {
                $dispatcher = new EventDispatcher();
                $dispatcher->addListener(MessageEvent::class, 'var_dump');
                $dispatcher->addListener('console.command', 'var_dump');

                return $dispatcher;
            },
            'other_event_dispatcher' => function () {
                $dispatcher = new EventDispatcher();
                $dispatcher->addListener('other_event', 'var_dump');
                $dispatcher->addListener('App\OtherEvent', 'var_dump');

                return $dispatcher;
            },
        ]);
        $command = new EventDispatcherDebugCommand($dispatchers);

        return new CommandCompletionTester($command);
    }
}
