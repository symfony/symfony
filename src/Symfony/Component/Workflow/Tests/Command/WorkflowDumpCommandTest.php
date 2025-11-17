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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Workflow\Command\WorkflowDumpCommand;

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
}
