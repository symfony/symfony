<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Output\BashCompletionOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CompleteCommandTest extends TestCase
{
    private CompleteCommand $command;
    private Application $application;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new CompleteCommand();

        $this->application = new Application();
        $this->application->add(new CompleteCommandTest_HelloCommand());

        $this->command->setApplication($this->application);
        $this->tester = new CommandTester($this->command);
    }

    public function testRequiredShellOption()
    {
        $this->expectExceptionMessage('The "--shell" option must be set.');
        $this->execute([]);
    }

    public function testUnsupportedShellOption()
    {
        $this->expectExceptionMessage('Shell completion is not supported for your shell: "unsupported" (supported: "bash", "fish", "zsh").');
        $this->execute(['--shell' => 'unsupported']);
    }

    public function testAdditionalShellSupport()
    {
        $this->command = new CompleteCommand(['supported' => BashCompletionOutput::class]);
        $this->command->setApplication($this->application);
        $this->tester = new CommandTester($this->command);

        $this->execute(['--shell' => 'supported', '--current' => '1', '--input' => ['bin/console']]);

        // verify that the default set of shells is still supported
        $this->execute(['--shell' => 'bash', '--current' => '1', '--input' => ['bin/console']]);

        $this->assertTrue(true);
    }

    /**
     * @dataProvider provideInputAndCurrentOptionValues
     */
    public function testInputAndCurrentOptionValidation(array $input, ?string $exceptionMessage)
    {
        if ($exceptionMessage) {
            $this->expectExceptionMessage($exceptionMessage);
        }

        $this->execute($input + ['--shell' => 'bash']);

        if (!$exceptionMessage) {
            $this->tester->assertCommandIsSuccessful();
        }
    }

    public static function provideInputAndCurrentOptionValues()
    {
        yield [[], 'The "--current" option must be set and it must be an integer'];
        yield [['--current' => 'a'], 'The "--current" option must be set and it must be an integer'];
        yield [['--current' => '1', '--input' => ['bin/console']], null];
        yield [['--current' => '2', '--input' => ['bin/console']], 'Current index is invalid, it must be the number of input tokens or one more.'];
        yield [['--current' => '1', '--input' => ['bin/console', 'cache:clear']], null];
        yield [['--current' => '2', '--input' => ['bin/console', 'cache:clear']], null];
    }

    /**
     * @dataProvider provideCompleteCommandNameInputs
     */
    public function testCompleteCommandName(array $input, array $suggestions)
    {
        $this->execute(['--current' => '1', '--input' => $input]);
        $this->assertEquals(implode("\n", $suggestions).\PHP_EOL, $this->tester->getDisplay());
    }

    public static function provideCompleteCommandNameInputs()
    {
        yield 'empty' => [['bin/console'], ['help', 'list', 'completion', 'hello', 'ahoy']];
        yield 'partial' => [['bin/console', 'he'], ['help', 'list', 'completion', 'hello', 'ahoy']];
        yield 'complete-shortcut-name' => [['bin/console', 'hell'], ['hello', 'ahoy']];
        yield 'complete-aliases' => [['bin/console', 'ah'], ['hello', 'ahoy']];
    }

    /**
     * @dataProvider provideCompleteCommandInputDefinitionInputs
     */
    public function testCompleteCommandInputDefinition(array $input, array $suggestions)
    {
        $this->execute(['--current' => '2', '--input' => $input]);
        $this->assertEquals(implode("\n", $suggestions).\PHP_EOL, $this->tester->getDisplay());
    }

    public static function provideCompleteCommandInputDefinitionInputs()
    {
        yield 'definition' => [['bin/console', 'hello', '-'], ['--help', '--quiet', '--verbose', '--version', '--ansi', '--no-ansi', '--no-interaction']];
        yield 'custom' => [['bin/console', 'hello'], ['Fabien', 'Robin', 'Wouter']];
        yield 'definition-aliased' => [['bin/console', 'ahoy', '-'], ['--help', '--quiet', '--verbose', '--version', '--ansi', '--no-ansi', '--no-interaction']];
        yield 'custom-aliased' => [['bin/console', 'ahoy'], ['Fabien', 'Robin', 'Wouter']];
    }

    private function execute(array $input)
    {
        // run in verbose mode to assert exceptions
        $this->tester->execute($input ? ($input + ['--shell' => 'bash', '--api-version' => CompleteCommand::COMPLETION_API_VERSION]) : $input, ['verbosity' => OutputInterface::VERBOSITY_DEBUG]);
    }
}

class CompleteCommandTest_HelloCommand extends Command
{
    public function configure(): void
    {
        $this->setName('hello')
             ->setAliases(['ahoy'])
             ->setDescription('Hello test command')
             ->addArgument('name', InputArgument::REQUIRED)
        ;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(['Fabien', 'Robin', 'Wouter']);
        }
    }
}
