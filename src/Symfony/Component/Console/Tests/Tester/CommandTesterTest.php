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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AskChoice;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tests\Fixtures\InvokableExtendingCommandTestCommand;
use Symfony\Component\Console\Tests\Fixtures\InvokableTestCommand;
use Symfony\Component\Console\Tests\Fixtures\InvokableWithInputTestCommand;
use Symfony\Component\Console\Tests\Fixtures\InvokableWithInteractiveAttributesTestCommand;
use Symfony\Component\Console\Tests\Fixtures\InvokableWithInteractiveChoiceAttributeTestCommand;
use Symfony\Component\Console\Tests\Fixtures\InvokableWithInteractiveHiddenQuestionAttributeTestCommand;
use Symfony\Component\Console\Tests\Fixtures\MethodBasedTestCommand;

class CommandTesterTest extends TestCase
{
    use ConsoleAssertionsTrait;

    protected Command $command;
    protected CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new Command('foo');
        $this->command->addArgument('command');
        $this->command->addArgument('foo');
        $this->command->setCode(static function (OutputInterface $output): int {
            $output->writeln('foo');

            return 0;
        });

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
        $command->setCode(static function (OutputInterface $output): int {
            $output->writeln('foo');

            return 0;
        });

        $application->addCommand($command);

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
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($questions, $command): int {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new Question($questions[0]));
            $helper->ask($input, $output, new Question($questions[1]));
            $helper->ask($input, $output, new Question($questions[2]));

            return 0;
        });

        $tester = new CommandTester($command);
        $tester->setInputs(['Bobby', 'Fine', 'France']);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertEquals(implode('', $questions), $tester->getDisplay(true));
    }

    public function testCommandWithMultilineInputs()
    {
        $question = 'What is your address?';

        $command = new Command('foo');
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($question, $command): int {
            $output->write($command->getHelper('question')->ask($input, $output, (new Question($question))->setMultiline(true)));
            $output->write(stream_get_contents($input->getStream()));

            return 0;
        });

        $tester = new CommandTester($command);

        $address = <<<ADDRESS
            31 Spooner Street
            Quahog
            ADDRESS;
        $tester->setInputs([$address."\x04", $address]);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame($question.$address.$address.\PHP_EOL, $tester->getDisplay());
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
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($questions, $command): int {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new Question($questions[0], 'Bobby'));
            $helper->ask($input, $output, new Question($questions[1], 'Fine'));
            $helper->ask($input, $output, new Question($questions[2], 'France'));

            return 0;
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
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($questions, $command): int {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new ChoiceQuestion('choice', ['a', 'b']));
            $helper->ask($input, $output, new Question($questions[0]));
            $helper->ask($input, $output, new Question($questions[1]));
            $helper->ask($input, $output, new Question($questions[2]));

            return 0;
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
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($questions, $command): int {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new ChoiceQuestion('choice', ['a', 'b']));
            $helper->ask($input, $output, new Question($questions[0]));
            $helper->ask($input, $output, new Question($questions[1]));
            $helper->ask($input, $output, new Question($questions[2]));

            return 0;
        });

        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aborted');

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
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($questions): int {
            $io = new SymfonyStyle($input, $output);
            $io->ask($questions[0]);
            $io->ask($questions[1]);
            $io->ask($questions[2]);

            return 0;
        });

        $tester = new CommandTester($command);
        $tester->setInputs(['Bobby', 'Fine', 'France']);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    public function testErrorOutput()
    {
        $command = new Command('foo');
        $command->addArgument('command');
        $command->addArgument('foo');
        $command->setCode(static function (OutputInterface $output): int {
            $output->getErrorOutput()->write('foo');

            return 0;
        });

        $tester = new CommandTester($command);
        $tester->execute(
            ['foo' => 'bar'],
            ['capture_stderr_separately' => true]
        );

        $this->assertSame('foo', $tester->getErrorOutput());
    }

    public function testAInvokableCommand()
    {
        $command = new InvokableTestCommand();

        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    public function testAInvokableExtendedCommand()
    {
        $command = new InvokableExtendingCommandTestCommand();

        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    public function testCallableMethodCommands()
    {
        $command = new MethodBasedTestCommand();

        $tester = new CommandTester($command);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->assertSame('cmd0', $tester->getDisplay());

        $tester = new CommandTester($command->cmd1(...));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->assertSame('cmd1', $tester->getDisplay());

        $tester = new CommandTester($command->cmd2(...));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->assertSame('cmd2', $tester->getDisplay());
    }

    public function testInvokableDefinitionWithInputAttribute()
    {
        $application = new Application();
        $application->addCommand(new InvokableWithInputTestCommand());
        $application->setAutoExit(false);

        $bufferedOutput = new BufferedOutput();
        $statusCode = $application->run(new ArrayInput(['command' => 'help', 'command_name' => 'invokable:input:test']), $bufferedOutput);

        $expectedOutput = <<<TXT
            Usage:
              invokable:input:test [options] [--] <username> <email> <password>

            Arguments:
              username %S
              email %S
              password %S

            Options:
                  --group=GROUP                           [default: "users"]
                  --group-description=GROUP-DESCRIPTION   [default: "Standard Users"]
                  --admin %S
                  --active|--no-active %S
                  --status=STATUS                         [default: "unverified"]
            %A
            TXT;

        self::assertSame(0, $statusCode);
        self::assertStringMatchesFormat($expectedOutput, $bufferedOutput->fetch());
    }

    public function testMethodBasedCommandWithApplication()
    {
        $command = new MethodBasedTestCommand();

        $application = new Application();
        $application->addCommand($command->cmd1(...));
        $application->setAutoExit(false);

        $bufferedOutput = new BufferedOutput();
        $statusCode = $application->run(new ArrayInput(['command' => 'help', 'command_name' => 'app:cmd1']), $bufferedOutput);

        $expectedOutput = <<<TXT
            Usage:
              app:cmd1 [<name>]

            Arguments:
              name %S
            %A
            TXT;

        self::assertSame(0, $statusCode);
        self::assertStringMatchesFormat($expectedOutput, $bufferedOutput->fetch());
    }

    #[DataProvider('getInvokableWithInputData')]
    public function testInvokableWithInputAttribute(array $input, string $output)
    {
        $command = new InvokableWithInputTestCommand();

        $tester = new CommandTester($command);
        $tester->execute($input);

        $tester->assertCommandIsSuccessful();
        self::assertSame($output, $tester->getDisplay(true));
    }

    public static function getInvokableWithInputData(): iterable
    {
        yield 'all set' => [
            'input' => [
                'username' => 'user1',
                'email' => 'user1@example.com',
                'password' => 'password123',
                '--admin' => true,
                '--active' => false,
                '--status' => 'verified',
                '--group' => 'admins',
                '--group-description' => 'Super Administrators',
            ],
            'output' => <<<TXT
                user1
                user1@example.com
                password123
                yes
                no
                verified
                admins
                Super Administrators

                TXT,
        ];

        yield 'only required arguments' => [
            'input' => [
                'username' => 'test',
                'email' => 'test@example.com',
                'password' => 'password123',
            ],
            'output' => <<<TXT
                test
                test@example.com
                password123
                no
                yes
                unverified
                users
                Standard Users

                TXT,
        ];

        yield 'admin enabled with defaults' => [
            'input' => [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => 'admin123',
                '--admin' => true,
            ],
            'output' => <<<TXT
                admin
                admin@example.com
                admin123
                yes
                yes
                unverified
                users
                Standard Users

                TXT,
        ];

        yield 'custom group with defaults' => [
            'input' => [
                'username' => 'user',
                'email' => 'user@custom.com',
                'password' => 'custom123',
                '--group' => 'moderators',
                '--group-description' => 'System Moderators',
            ],
            'output' => <<<TXT
                user
                user@custom.com
                custom123
                no
                yes
                unverified
                moderators
                System Moderators

                TXT,
        ];

        yield 'defaults with interactive' => [
            'input' => [
                'username' => 'user',
            ],
            'output' => <<<TXT
                user
                user.interactive@command.com
                user-dto-interactive-password
                no
                yes
                unverified
                users
                Standard Users

                TXT,
        ];
    }

    public function testInvokableWithInteractiveQuestionParameter()
    {
        $tester = new CommandTester(new InvokableWithInteractiveAttributesTestCommand());
        $tester->setInputs(['arg1-value', 'arg2-value', 'arg3-value', 'arg6-value', 'arg7-value', 'yes', 'arg9-v1', 'arg9-v2', '', 'arg4-value', 'arg5-value']);
        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('Enter arg1', $tester->getDisplay());
        self::assertStringContainsString('Arg1: arg1-value', $tester->getDisplay());
        self::assertStringContainsString('Enter arg2', $tester->getDisplay());
        self::assertStringContainsString('Arg2: arg2-value', $tester->getDisplay());
        self::assertStringContainsString('Enter arg3', $tester->getDisplay());
        self::assertStringContainsString('Arg3: arg3-value', $tester->getDisplay());
        self::assertStringContainsString('Enter arg6', $tester->getDisplay());
        self::assertStringContainsString('Arg6: arg6-value', $tester->getDisplay());
        self::assertStringContainsString('Enter arg7', $tester->getDisplay());
        self::assertStringContainsString('Arg7: arg7-value', $tester->getDisplay());
        self::assertStringContainsString('Enter arg8 (yes/no) [no]', $tester->getDisplay());
        self::assertStringContainsString('Arg8: yes', $tester->getDisplay());
        self::assertStringContainsString('Enter arg9', $tester->getDisplay());
        self::assertStringContainsString('Arg9: arg9-v1,arg9-v2', $tester->getDisplay());
        self::assertStringContainsString('Enter arg4', $tester->getDisplay());
        self::assertStringContainsString('Arg4: arg4-value', $tester->getDisplay());
        self::assertStringContainsString('Enter arg5', $tester->getDisplay());
        self::assertStringContainsString('Arg5: arg5-value', $tester->getDisplay());
    }

    public function testInvokableWithInteractiveHiddenQuestionParameter()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Cannot test hidden questions on Windows');
        }

        $tester = new CommandTester(new InvokableWithInteractiveHiddenQuestionAttributeTestCommand());
        $tester->setInputs(['arg1-value']);
        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('Enter arg1', $tester->getDisplay());
        self::assertStringContainsString('Arg1: arg1-value', $tester->getDisplay());
    }

    public function testInvokableWithInteractiveChoiceAttribute()
    {
        $tester = new CommandTester(new InvokableWithInteractiveChoiceAttributeTestCommand());
        $tester->setInputs(['green', '', 'active', 'auth,cache']);
        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('Select a color', $tester->getDisplay());
        self::assertStringContainsString('Color: green', $tester->getDisplay());
        self::assertStringContainsString('Select a size', $tester->getDisplay());
        self::assertStringContainsString('Size: medium', $tester->getDisplay());
        self::assertStringContainsString('Select a status', $tester->getDisplay());
        self::assertStringContainsString('Status: active', $tester->getDisplay());
        self::assertStringContainsString('Select features', $tester->getDisplay());
        self::assertStringContainsString('Features: auth,cache', $tester->getDisplay());
    }

    public function testInvokableWithInteractiveChoiceAttributeNonDefaultValues()
    {
        $tester = new CommandTester(new InvokableWithInteractiveChoiceAttributeTestCommand());
        $tester->setInputs(['blue', 'large', 'pending', 'api']);
        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('Color: blue', $tester->getDisplay());
        self::assertStringContainsString('Size: large', $tester->getDisplay());
        self::assertStringContainsString('Status: pending', $tester->getDisplay());
        self::assertStringContainsString('Features: api', $tester->getDisplay());
    }

    public function testInvokableWithInteractiveChoiceAttributeInvalidThenValid()
    {
        $tester = new CommandTester(new InvokableWithInteractiveChoiceAttributeTestCommand());
        // First input 'yellow' is invalid, then 'red' is valid
        $tester->setInputs(['yellow', 'red', 'medium', 'active', 'auth']);
        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('Value "yellow" is invalid', $tester->getDisplay());
        self::assertStringContainsString('Color: red', $tester->getDisplay());
    }

    public function testInvokableWithInteractiveChoiceAttributeInvalidEnumValue()
    {
        $tester = new CommandTester(new InvokableWithInteractiveChoiceAttributeTestCommand());
        // 'unknown' is not a valid enum value, then 'inactive' is valid
        $tester->setInputs(['red', 'medium', 'unknown', 'inactive', 'api']);
        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('Value "unknown" is invalid', $tester->getDisplay());
        self::assertStringContainsString('Status: inactive', $tester->getDisplay());
    }

    public function testInvokableWithInteractiveChoiceAttributeInvalidChoiceNumber()
    {
        $tester = new CommandTester(new InvokableWithInteractiveChoiceAttributeTestCommand());
        // '5' is not a valid choice number, then '1' (inactive) is valid
        $tester->setInputs(['red', 'medium', '5', '1', 'api']);
        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        self::assertStringContainsString('Value "5" is invalid', $tester->getDisplay());
        self::assertStringContainsString('Status: inactive', $tester->getDisplay());
    }

    public function testChoiceWithoutChoicesAndWithoutEnumThrowsException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires either explicit choices or a BackedEnum type');

        $command = new Command('foo');
        $command->setCode(static fn (
            #[Argument, AskChoice('Select a color')]
            string $color,
        ): int => 0);

        $command->getDefinition();
    }

    public function testItExecutesTheTestedCommandWithTheSameConfigAsThePreviousApiByDefault()
    {
        $oldConfig = [];
        $newConfig = [];

        $oldTesterCommand = self::createDisplayConfigurationCommand($oldConfig);
        $newTesterCommand = self::createDisplayConfigurationCommand($newConfig);

        $oldTester = new CommandTester($oldTesterCommand);
        $oldTester->execute([]);

        $newTester = new CommandTester($newTesterCommand);
        $newTester->run();

        // Sanity check
        $this->assertNotEquals([], $oldConfig);
        $this->assertEquals($oldConfig, $newConfig);
    }

    public function testItCanConfigureTheExecutedCommand()
    {
        $config = [];

        $test = new CommandTester(
            self::createDisplayConfigurationCommand($config),
        );
        $test->run(
            interactive: true,
            decorated: false,
            verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE,
        );

        $expectedConfig = [
            'interactive' => true,
            'decorated' => false,
            'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
        ];

        $this->assertEquals($expectedConfig, $config);
    }

    public function testItCanTestTheExecutionResult()
    {
        $command = new Command('foo');
        $command->setCode(static function (InputInterface $input, OutputInterface $output) {
            $output->writeln('bar');

            return 0;
        });

        $result = (new CommandTester($command))->run();

        $this->assertIsSuccessful($result);
        $this->assertSame(0, $result->statusCode);
        $this->assertSame("bar\n", $result->getDisplay());
    }

    public function testItProvidesUserInputs()
    {
        $questions = [
            'What\'s your name?',
            'How are you?',
            'Where do you come from?',
        ];

        $command = new Command('foo');
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($questions, $command): int {
            $helper = $command->getHelper('question');
            $helper->ask($input, $output, new Question($questions[0]));
            $helper->ask($input, $output, new Question($questions[1]));
            $helper->ask($input, $output, new Question($questions[2]));

            return 0;
        });

        $tester = new CommandTester($command);
        $result = $tester->run(interactiveInputs: ['Bobby', 'Fine', 'France']);

        $this->assertResultEquals(
            $result,
            0,
            '',
            implode('', $questions),
            implode('', $questions),
        );
    }

    private static function createDisplayConfigurationCommand(array &$config): Command
    {
        $command = new Command('foo');
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use (&$config) {
            $config['interactive'] = $input->isInteractive();
            $config['verbosity'] = $output->getVerbosity();
            $config['decorated'] = $output->isDecorated();

            return 0;
        });

        return $command;
    }
}
