<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Tester;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTesterTest extends TestCase
{
    protected Command $command;
    protected CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new Command('foo');
        $this->command->addArgument('command', InputArgument::REQUIRED, Application::COMMAND_ARGUMENT_DESCRIPTION);
        $this->command->addArgument('foo');
        $this->command->setCode(function ($input, $output) { $output->writeln('foo'); });

        $this->tester = new CommandTester($this->command);
        $this->tester->execute(['foo' => 'bar'], ['interactive' => false, 'decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE]);
    }

    public function testExecute()
    {
        $this->assertFalse($this->tester->getInput()->isInteractive(), '->execute() takes an interactive option');
        $this->assertFalse($this->tester->getOutput()->isDecorated(), '->execute() takes a decorated option');
        $this->assertEquals(Output::VERBOSITY_VERBOSE, $this->tester->getOutput()->getVerbosity(), '->execute() takes a verbosity option');
    }

    public function testGetInput()
    {
        $this->assertEquals('bar', $this->tester->getInput()->getArgument('foo'), '->getInput() returns the current input instance');
    }

    public function testGetOutput()
    {
        rewind($this->tester->getOutput()->getStream());
        $this->assertEquals('foo'.\PHP_EOL, stream_get_contents($this->tester->getOutput()->getStream()), '->getOutput() returns the current output instance');
    }

    public function testGetDisplay()
    {
        $this->assertEquals('foo'.\PHP_EOL, $this->tester->getDisplay(), '->getDisplay() returns the display of the last execution');
    }

    public function testGetDisplayWithoutCallingExecuteBefore()
    {
        $tester = new CommandTester(new Command());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Output not initialized');

        $tester->getDisplay();
    }

    public function testGetStatusCode()
    {
        $this->tester->assertCommandIsSuccessful('->getStatusCode() returns the status code');
    }

    public function testGetStatusCodeWithoutCallingExecuteBefore()
    {
        $tester = new CommandTester(new Command());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Status code not initialized');

        $tester->getStatusCode();
    }

    public function testCommandFromApplication()
    {
        $application = new Application();
        $application->setAutoExit(false);

        $command = new Command('foo');
        $command->setCode(function ($input, $output) { $output->writeln('foo'); });

        $application->add($command);

        $tester = new CommandTester($application->find('foo'));

        // check that there is no need to pass the command name here
        $this->assertEquals(0, $tester->execute([]));
    }

    public function testCommandWithInputs()
    {
        $questions = [
            'What\'s your name?',
            'How are you?',
            'Where do you come from?',
        ];

        $command = new Command('foo');
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $command->setCode(function ($input, $output) use ($questions, $command) {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new Question($questions[0]));
            $helper->ask($input, $output, new Question($questions[1]));
            $helper->ask($input, $output, new Question($questions[2]));
        });

        $tester = new CommandTester($command);
        $tester->setInputs(['Bobby', 'Fine', 'France']);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertEquals(implode('', $questions), $tester->getDisplay(true));
    }

    public function testCommandWithDefaultInputs()
    {
        $questions = [
            'What\'s your name?',
            'How are you?',
            'Where do you come from?',
        ];

        $command = new Command('foo');
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $command->setCode(function ($input, $output) use ($questions, $command) {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new Question($questions[0], 'Bobby'));
            $helper->ask($input, $output, new Question($questions[1], 'Fine'));
            $helper->ask($input, $output, new Question($questions[2], 'France'));
        });

        $tester = new CommandTester($command);
        $tester->setInputs(['', '', '']);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertEquals(implode('', $questions), $tester->getDisplay(true));
    }

    public function testCommandWithWrongInputsNumber()
    {
        $questions = [
            'What\'s your name?',
            'How are you?',
            'Where do you come from?',
        ];

        $command = new Command('foo');
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $command->setCode(function ($input, $output) use ($questions, $command) {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new ChoiceQuestion('choice', ['a', 'b']));
            $helper->ask($input, $output, new Question($questions[0]));
            $helper->ask($input, $output, new Question($questions[1]));
            $helper->ask($input, $output, new Question($questions[2]));
        });

        $tester = new CommandTester($command);
        $tester->setInputs(['a', 'Bobby', 'Fine']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aborted.');

        $tester->execute([]);
    }

    public function testCommandWithQuestionsButNoInputs()
    {
        $questions = [
            'What\'s your name?',
            'How are you?',
            'Where do you come from?',
        ];

        $command = new Command('foo');
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $command->setCode(function ($input, $output) use ($questions, $command) {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new ChoiceQuestion('choice', ['a', 'b']));
            $helper->ask($input, $output, new Question($questions[0]));
            $helper->ask($input, $output, new Question($questions[1]));
            $helper->ask($input, $output, new Question($questions[2]));
        });

        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aborted.');

        $tester->execute([]);
    }

    public function testSymfonyStyleCommandWithInputs()
    {
        $questions = [
            'What\'s your name?',
            'How are you?',
            'Where do you come from?',
        ];

        $command = new Command('foo');
        $command->setCode(function ($input, $output) use ($questions) {
            $io = new SymfonyStyle($input, $output);
            $io->ask($questions[0]);
            $io->ask($questions[1]);
            $io->ask($questions[2]);
        });

        $tester = new CommandTester($command);
        $tester->setInputs(['Bobby', 'Fine', 'France']);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    public function testErrorOutput()
    {
        $command = new Command('foo');
        $command->addArgument('command', InputArgument::REQUIRED, Application::COMMAND_ARGUMENT_DESCRIPTION);
        $command->addArgument('foo');
        $command->setCode(function ($input, $output) {
            $output->getErrorOutput()->write('foo');
        });

        $tester = new CommandTester($command);
        $tester->execute(
            ['foo' => 'bar'],
            ['capture_stderr_separately' => true]
        );

        $this->assertSame('foo', $tester->getErrorOutput());
    }
}
