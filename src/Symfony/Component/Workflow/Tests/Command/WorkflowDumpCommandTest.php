<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Command\WorkflowDumpCommand;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class WorkflowDumpCommandTest extends TestCase
{
    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application();
        $application->addCommand(new WorkflowDumpCommand(new ServiceLocator([])));

        $tester = new CommandCompletionTester($application->find('workflow:dump'));
        $suggestions = $tester->complete($input, 2);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'option --dump-format' => [['--dump-format', ''], ['puml', 'mermaid', 'dot']];
    }

    public function testWithListenersFoldsListenersIntoTransitionMetadata()
    {
        $transition = new Transition('t1', 'a', 'b');
        $definition = new Definition(['a', 'b'], [$transition]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.wf.transition.t1', static function () {}, 0);

        $workflow = new Workflow($definition, null, $dispatcher, 'wf');
        $locator = new ServiceLocator(['wf' => static fn () => $workflow]);

        $command = new WorkflowDumpCommand($locator, $dispatcher);
        $tester = new CommandTester($command);
        $tester->execute(['name' => 'wf', '--with-listeners' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Listener #0', $output);
    }

    public function testWithListenersWithoutDispatcherThrows()
    {
        $transition = new Transition('t1', 'a', 'b');
        $definition = new Definition(['a', 'b'], [$transition]);
        $workflow = new Workflow($definition, null, null, 'wf');
        $locator = new ServiceLocator(['wf' => static fn () => $workflow]);

        $command = new WorkflowDumpCommand($locator);
        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot use the "--with-listeners" option if a dispatcher is not injected in the constructor.');

        $tester->execute(['name' => 'wf', '--with-listeners' => true]);
    }

    public function testWithListenersWithoutFlagDoesNotInvokeExtractor()
    {
        $transition = new Transition('t1', 'a', 'b');
        $definition = new Definition(['a', 'b'], [$transition]);
        $workflow = new Workflow($definition, null, null, 'wf');
        $locator = new ServiceLocator(['wf' => static fn () => $workflow]);

        // No dispatcher injected; without --with-listeners the command still runs.
        $command = new WorkflowDumpCommand($locator);
        $tester = new CommandTester($command);
        $status = $tester->execute(['name' => 'wf']);

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString('Listener #', $tester->getDisplay());
    }
}
