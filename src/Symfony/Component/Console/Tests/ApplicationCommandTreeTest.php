<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandChain;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ApplicationCommandTreeTest extends TestCase
{
    private array $runs = [];

    private function createTreeApplication(): Application
    {
        $this->runs = [];

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $docker = new Command('docker');
        $docker->addOption('context', 'c', InputOption::VALUE_REQUIRED);

        $compose = new Command('docker:compose');
        $compose->addOption('file', 'f', InputOption::VALUE_REQUIRED);
        $compose->addArgument('project', InputArgument::OPTIONAL, '', null, ['api', 'web']);

        $up = new Command('docker:compose:up');
        $up->addArgument('service', InputArgument::OPTIONAL, '', null, ['web', 'db']);
        $up->addOption('detach', 'd', InputOption::VALUE_NONE);
        $up->setCode(function (InputInterface $input) {
            $this->runs['docker:compose:up'] = $input;

            return 0;
        });

        $deploy = new Command('deploy');
        $deploy->addArgument('target', InputArgument::OPTIONAL, '', null, ['prod', 'staging']);
        $deploy->setCode(function (InputInterface $input) {
            $this->runs['deploy'] = $input;

            return 0;
        });

        $rollback = new Command('deploy:rollback');
        $rollback->setCode(function (InputInterface $input) {
            $this->runs['deploy:rollback'] = $input;

            return 0;
        });

        $leafB = new Command('tree:a:b');
        $leafB->addOption('fast', null, InputOption::VALUE_NONE);
        $leafB->setCode(function (InputInterface $input) {
            $this->runs['tree:a:b'] = $input;

            return 0;
        });

        $application->addCommand($docker);
        $application->addCommand($compose);
        $application->addCommand($up);
        $application->addCommand($deploy);
        $application->addCommand($rollback);
        $application->addCommand(new Command('tree'));
        $application->addCommand($leafB);

        $nsLeaf = new Command('ns:sub');
        $nsLeaf->setDescription('A leaf without a registered root');
        $nsLeaf->addOption('fast', null, InputOption::VALUE_NONE);
        $nsLeaf->setCode(function (InputInterface $input) {
            $this->runs['ns:sub'] = $input;

            return 0;
        });
        $application->addCommand($nsLeaf);

        return $application;
    }

    private function createConsoleOutput(): BufferedOutput&ConsoleOutputInterface
    {
        return new class extends BufferedOutput implements ConsoleOutputInterface {
            public BufferedOutput $errorOutput;

            public function __construct()
            {
                parent::__construct();
                $this->errorOutput = new BufferedOutput();
            }

            public function getErrorOutput(): OutputInterface
            {
                return $this->errorOutput;
            }

            public function setErrorOutput(OutputInterface $error): void
            {
                throw new \LogicException('Not supported.');
            }

            public function section(): ConsoleSectionOutput
            {
                throw new \LogicException('Not supported.');
            }
        };
    }

    public function testSpacedInvocationRunsTheLeaf()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', 'compose', 'up', 'web', '--detach']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('docker:compose:up', $this->runs);
        $this->assertSame('web', $this->runs['docker:compose:up']->getArgument('service'));
        $this->assertTrue($this->runs['docker:compose:up']->getOption('detach'));
    }

    public function testEachLevelParsesItsOwnOptions()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', '--context=prod', 'compose', '-f', 'app.yaml', 'up', 'web']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertSame('web', $this->runs['docker:compose:up']->getArgument('service'));
        $this->assertFalse($this->runs['docker:compose:up']->hasOption('file'));
    }

    public function testApplicationOptionsBoundAtAnAncestorLevelReachTheLeaf()
    {
        $application = $this->createTreeApplication();
        $application->getDefinition()->addOption(new InputOption('stage', null, InputOption::VALUE_REQUIRED));

        $application->run(new ArgvInput(['cli.php', 'docker', '-v', '--stage=blue', 'compose', '--no-ansi', 'up']), new NullOutput());

        $input = $this->runs['docker:compose:up'];
        $this->assertTrue($input->getOption('verbose'));
        $this->assertSame('blue', $input->getOption('stage'));
        $this->assertFalse($input->getOption('ansi'));
        $this->assertTrue($input->hasParameterOption('--stage'));
        $this->assertStringContainsString('--verbose --stage=blue --no-ansi', (string) $input);
    }

    public function testUnknownOptionBeforeABranchFailsOnThatLevel()
    {
        $application = $this->createTreeApplication();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "--nope" option does not exist.');

        $application->run(new ArgvInput(['cli.php', 'docker', '--nope', 'compose', 'up']), new NullOutput());
    }

    public function testLeafKeepsStrictParsing()
    {
        $application = $this->createTreeApplication();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many arguments');

        $application->run(new ArgvInput(['cli.php', 'docker', 'compose', 'up', 'web', 'extra']), new NullOutput());
    }

    public function testSubCommandsBindBeforeArgumentSlots()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'deploy', 'rollback']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('deploy:rollback', $this->runs);
        $this->assertArrayNotHasKey('deploy', $this->runs);
    }

    public function testDoubleDashBindsArgumentsToTheCurrentNode()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'deploy', '--', 'rollback']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('deploy', $this->runs);
        $this->assertSame('rollback', $this->runs['deploy']->getArgument('target'));
        $this->assertArrayNotHasKey('deploy:rollback', $this->runs);
    }

    public function testNodeWithCodeStillRunsWhenNoSubCommandIsGiven()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'deploy']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('deploy', $this->runs);
        $this->assertNull($this->runs['deploy']->getArgument('target'));
    }

    public function testATokenNamingNoSubCommandIsTheNodeArgument()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'deploy', 'prod']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertSame('prod', $this->runs['deploy']->getArgument('target'));
        $this->assertArrayNotHasKey('deploy:rollback', $this->runs);
    }

    public function testATokenNamingNoSubCommandBelowTheRootIsThatNodeArgument()
    {
        $application = $this->createTreeApplication();
        $application->get('docker:compose')->setCode(function (InputInterface $input) {
            $this->runs['docker:compose'] = $input;

            return 0;
        });

        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', 'compose', '-f', 'app.yaml', 'api']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertSame('api', $this->runs['docker:compose']->getArgument('project'));
        $this->assertSame('app.yaml', $this->runs['docker:compose']->getOption('file'));
        $this->assertArrayNotHasKey('docker:compose:up', $this->runs);
    }

    public function testUnknownSegmentGetsScopedAlternatives()
    {
        $application = $this->createTreeApplication();

        try {
            $application->run(new ArgvInput(['cli.php', 'docker', 'compos']), new NullOutput());
            $this->fail('A CommandNotFoundException should have been thrown.');
        } catch (CommandNotFoundException $e) {
            $this->assertStringContainsString('There is no command "compos" under "docker".', $e->getMessage());
            $this->assertStringNotContainsString(' -- ', $e->getMessage());
            $this->assertSame(['docker:compose'], $e->getAlternatives());
        }
    }

    public function testImplicitIntermediateLevelsRoute()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'tree', 'a', 'b', '--fast']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('tree:a:b', $this->runs);
        $this->assertTrue($this->runs['tree:a:b']->getOption('fast'));
    }

    public function testColonInvocationIsUnchanged()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'deploy:rollback']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('deploy:rollback', $this->runs);
    }

    public function testBareGroupWithoutCodeListsItsSubCommands()
    {
        $application = $this->createTreeApplication();

        $output = $this->createConsoleOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $output->fetch());
        $this->assertStringContainsString('docker:compose:up', $output->errorOutput->fetch());
    }

    public function testTerminalMidNodeWithoutCodeListsItsScope()
    {
        $application = $this->createTreeApplication();

        $output = $this->createConsoleOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', 'compose']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $output->fetch());
        $this->assertStringContainsString('docker:compose:up', $output->errorOutput->fetch());
    }

    public function testImplicitNodeListsItsScope()
    {
        $application = $this->createTreeApplication();

        $output = $this->createConsoleOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'tree', 'a']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $output->fetch());
        $this->assertStringContainsString('tree:a:b', $output->errorOutput->fetch());
    }

    public function testCodelessCommandWithoutDescendantsStillThrows()
    {
        $application = $this->createTreeApplication();
        $application->addCommand(new Command('solo'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You must override the execute() method');

        $application->run(new ArgvInput(['cli.php', 'solo']), new NullOutput());
    }

    public function testHelpOnALeafShowsThatLeaf()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', 'compose', 'up', '--help']), $output);
        $display = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('docker:compose:up', $display);
        $this->assertStringContainsString('--detach', $display);
        $this->assertStringNotContainsString('--context', $display);
    }

    public function testHelpOnAMidNodeShowsThatNode()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', 'compose', '--help']), $output);
        $display = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('--file', $display);
        $this->assertStringNotContainsString('--context', $display);
    }

    public function testHelpOnATreeRootShowsTheRoot()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', '--help']), $output);
        $display = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('--context', $display);
    }

    public function testTheLeafCanReadAncestorInputThroughTheChain()
    {
        $application = $this->createTreeApplication();

        $context = null;
        $application->get('docker:compose:up')->setCode(static function (CommandChain $chain) use (&$context): int {
            $context = $chain->getInput('docker')?->getOption('context');

            return 0;
        });

        $application->run(new ArgvInput(['cli.php', 'docker', '--context=prod', 'compose', 'up']), new NullOutput());

        $this->assertSame('prod', $context);
    }

    public function testTheChainIsExposedWhileACommandRuns()
    {
        $application = $this->createTreeApplication();

        $observed = [];
        $application->get('docker:compose:up')->setCode(static function () use ($application, &$observed): int {
            $chain = $application->getCommandChain();
            $observed['names'] = array_map(static fn ($c) => $c->getName(), $chain->getCommands());
            $observed['file'] = $chain->getInput('docker:compose')?->getOption('file');

            return 0;
        });

        $application->run(new ArgvInput(['cli.php', 'docker', 'compose', '-f', 'app.yaml', 'up']), new NullOutput());

        $this->assertSame(['docker', 'docker:compose', 'docker:compose:up'], $observed['names']);
        $this->assertSame('app.yaml', $observed['file']);
        $this->assertNull($application->getCommandChain());
    }

    public function testAFlatRunExposesASingleLevelChain()
    {
        $application = $this->createTreeApplication();

        $observed = null;
        $application->get('deploy:rollback')->setCode(static function () use ($application, &$observed): int {
            $observed = array_map(static fn ($c) => $c->getName(), $application->getCommandChain()->getCommands());

            return 0;
        });

        $application->run(new ArgvInput(['cli.php', 'deploy:rollback']), new NullOutput());

        $this->assertSame(['deploy:rollback'], $observed);
    }

    public function testCompletionSuggestsChildSegments()
    {
        $this->assertSame(['compose'], $this->complete(['bin/console', 'docker', ''], 2));
        $this->assertSame(['compose'], $this->complete(['bin/console', 'docker', 'comp'], 2));
        $this->assertSame(['b'], $this->complete(['bin/console', 'tree', 'a', ''], 3));
    }

    public function testCompletionOffersTheNodeArgumentValuesNextToTheSegments()
    {
        $this->assertSame(['rollback', 'prod', 'staging'], $this->complete(['bin/console', 'deploy', ''], 2));
        $this->assertSame(['up', 'api', 'web'], $this->complete(['bin/console', 'docker', 'compose', ''], 3));
        $this->assertSame(['up', 'api', 'web'], $this->complete(['bin/console', 'docker', 'compose', 'u'], 3));
    }

    public function testCompletionSuggestsTheCurrentLevelOptions()
    {
        $this->assertContains('--context', $this->complete(['bin/console', 'docker', '-'], 2));
    }

    public function testCompletionOfALeafUsesTheLeafDefinition()
    {
        $suggestions = $this->complete(['bin/console', 'docker', 'compose', 'up', ''], 4);

        $this->assertContains('web', $suggestions);
        $this->assertContains('db', $suggestions);
    }

    private function complete(array $words, int $current, ?Application $application = null): array
    {
        $application ??= $this->createTreeApplication();
        $tester = new CommandTester($application->get('_complete'));
        $tester->execute(['--shell' => 'bash', '--api-version' => CompleteCommand::COMPLETION_API_VERSION, '--input' => $words, '--current' => (string) $current]);

        return array_values(array_filter(array_map(static fn ($s) => explode("\t", $s)[0], explode("\n", $tester->getDisplay(true)))));
    }

    public function testTheRootListingCollapsesTrees()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $application->run(new ArgvInput(['cli.php', 'list']), $output);
        $display = $output->fetch();

        $this->assertStringContainsString('docker', $display);
        $this->assertStringContainsString('deploy', $display);
        $this->assertStringNotContainsString('docker:compose', $display);
        $this->assertStringNotContainsString('deploy:rollback', $display);
    }

    public function testTheNamespaceListingKeepsTreeCommands()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $application->run(new ArgvInput(['cli.php', 'list', 'docker']), $output);

        $this->assertStringContainsString('docker:compose:up', $output->fetch());
    }

    public function testTheHelpOfANodeListsItsSubCommands()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $application->run(new ArgvInput(['cli.php', 'help', 'docker:compose']), $output);
        $display = $output->fetch();

        $this->assertStringContainsString('Available sub-commands:', $display);
        $this->assertStringContainsString('up', $display);
    }

    public function testSpacedInvocationThroughApplicationTester()
    {
        $application = $this->createTreeApplication();

        $tester = new ApplicationTester($application);
        $statusCode = $tester->run(['command' => 'docker', '--context' => 'prod', 'compose', 'up', 'web']);

        $this->assertSame(0, $statusCode);
        $this->assertArrayHasKey('docker:compose:up', $this->runs);
        $this->assertSame('web', $this->runs['docker:compose:up']->getArgument('service'));
    }

    public function testANamespaceWithoutARegisteredRootIsWalkable()
    {
        $application = $this->createTreeApplication();

        $exitCode = $application->run(new ArgvInput(['cli.php', 'ns', 'sub', '--fast']), new NullOutput());

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('ns:sub', $this->runs);
        $this->assertTrue($this->runs['ns:sub']->getOption('fast'));
    }

    public function testABareNamespaceWithoutARegisteredRootListsItsCommands()
    {
        $application = $this->createTreeApplication();

        $output = $this->createConsoleOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'ns']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $output->fetch());
        $this->assertStringContainsString('ns:sub', $output->errorOutput->fetch());
    }

    public function testAnUnknownSegmentUnderANamespaceGetsScopedSuggestions()
    {
        $application = $this->createTreeApplication();

        try {
            $application->run(new ArgvInput(['cli.php', 'ns', 'subb']), new NullOutput());
            $this->fail('A CommandNotFoundException should have been thrown.');
        } catch (CommandNotFoundException $e) {
            $this->assertStringContainsString('There is no command "subb" under "ns"', $e->getMessage());
            $this->assertSame(['ns:sub'], $e->getAlternatives());
        }
    }

    public function testCompletionWorksUnderANamespaceWithoutARegisteredRoot()
    {
        $this->assertSame(['sub'], $this->complete(['bin/console', 'ns', 's'], 2));
    }

    public function testCompletionOfAPartialCommandNameEndingWithAColon()
    {
        $suggestions = $this->complete(['bin/console', 'docker:'], 1);

        $this->assertContains('docker:compose', $suggestions);
        $this->assertContains('docker:compose:up', $suggestions);
    }

    public function testLeafInputInheritsInteractivity()
    {
        $application = $this->createTreeApplication();

        $input = new ArgvInput(['cli.php', 'docker', 'compose', 'up']);
        $input->setInteractive(false);
        $application->run($input, new NullOutput());

        $this->assertFalse($this->runs['docker:compose:up']->isInteractive());
    }

    public function testAnAliasAtAnIntermediateLevelWalksTheCanonicalName()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommand(new Command('docker'));
        $application->addCommand((new Command('docker:compose'))->setAliases(['docker:c']));
        $application->addCommand((new Command('docker:compose:up'))->setCode(function (InputInterface $input) {
            $this->runs['docker:compose:up'] = $input;

            return 0;
        }));

        $application->run(new ArgvInput(['cli.php', 'docker', 'c', 'up']), new NullOutput());

        $this->assertArrayHasKey('docker:compose:up', $this->runs);
        $this->assertSame(['up'], $this->complete(['bin/console', 'docker', 'c', 'u'], 3, $application));
    }

    public function testAnAliasUnderTheCommandOwnNameIsNotADescendant()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $deploy = new Command('deploy');
        $deploy->setAliases(['deploy:prod']);
        $deploy->addArgument('target', InputArgument::OPTIONAL);
        $deploy->setCode(function (InputInterface $input) {
            $this->runs['deploy'] = $input;

            return 0;
        });
        $application->addCommand($deploy);

        $application->run(new ArgvInput(['cli.php', 'deploy', 'prod']), new NullOutput());
        $this->assertSame('prod', $this->runs['deploy']->getArgument('target'));

        $application->run(new ArgvInput(['cli.php', 'deploy:prod', 'staging']), new NullOutput());
        $this->assertSame('staging', $this->runs['deploy']->getArgument('target'));

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setCommandLoader(new FactoryCommandLoader(['deploy' => static fn () => $deploy, 'deploy:prod' => static fn () => $deploy]));

        $application->run(new ArgvInput(['cli.php', 'deploy', 'prod']), new NullOutput());
        $this->assertSame('prod', $this->runs['deploy']->getArgument('target'));
    }

    public function testAnUnknownSegmentDispatchesTheErrorEvent()
    {
        $application = $this->createTreeApplication();
        $application->setCatchExceptions(true);
        $dispatcher = new EventDispatcher();
        $errors = [];
        $dispatcher->addListener(ConsoleEvents::ERROR, static function (ConsoleErrorEvent $event) use (&$errors) {
            $errors[] = $event->getError();
        });
        $application->setDispatcher($dispatcher);

        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', 'compos']), new BufferedOutput());

        $this->assertSame(1, $exitCode);
        $this->assertCount(1, $errors);
        $this->assertInstanceOf(CommandNotFoundException::class, $errors[0]);
    }

    public function testABareNamespaceHoldingOnlyHiddenCommandsReportsTheNamespace()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommand(new Command('ns'));
        $application->addCommand((new Command('ns:secret'))->setHidden(true)->setCode(static fn () => 0));

        $this->expectException(NamespaceNotFoundException::class);
        $this->expectExceptionMessage('There are no commands defined in the "ns" namespace.');

        $application->run(new ArgvInput(['cli.php', 'ns']), new NullOutput());
    }

    public function testHelpAcceptsASpacedPath()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'help', 'docker', 'compose']), $output);
        $display = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('docker:compose', $display);
        $this->assertStringContainsString('--file', $display);
        $this->assertStringNotContainsString('--context', $display);
    }

    public function testHelpAcceptsASpacedPathToAnImplicitNode()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'help', 'tree', 'a']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('tree:a', $output->fetch());
    }

    public function testHelpKeepsIgnoringTokensThatNameNoSubCommand()
    {
        $application = $this->createTreeApplication();

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'help', 'deploy:rollback', 'now']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('deploy:rollback', $output->fetch());
    }

    public function testHelpWrapsALazilyRegisteredHelpCommand()
    {
        $application = $this->createTreeApplication();
        $application->addCommand(new LazyCommand('help', [], '', false, static fn () => new HelpCommand()));

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArgvInput(['cli.php', 'docker', 'compose', '--help']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('docker:compose', $output->fetch());
    }

    public function testANestedRunRestoresTheChain()
    {
        $application = $this->createTreeApplication();

        $observed = null;
        $application->get('docker:compose:up')->setCode(static function () use ($application, &$observed): int {
            $application->doRun(new ArrayInput(['command' => 'list']), new NullOutput());
            $observed = array_map(static fn ($c) => $c->getName(), $application->getCommandChain()->getCommands());

            return 0;
        });

        $application->run(new ArgvInput(['cli.php', 'docker', 'compose', 'up']), new NullOutput());

        $this->assertSame(['docker', 'docker:compose', 'docker:compose:up'], $observed);
    }

    public function testACommandRunFromAnotherOneGetsItsOwnChain()
    {
        $application = $this->createTreeApplication();

        $observed = null;
        $application->addCommand((new Command('inner'))->setCode(static function (CommandChain $chain) use (&$observed): int {
            $observed = [array_map(static fn ($c) => $c->getName(), $chain->getCommands()), null !== $chain->getInput('inner')];

            return 0;
        }));
        $application->get('docker:compose:up')->setCode(static fn (OutputInterface $output) => $application->find('inner')->run(new ArrayInput([]), $output));

        $application->run(new ArgvInput(['cli.php', 'docker', 'compose', 'up']), new NullOutput());

        $this->assertSame([['inner'], true], $observed);
    }

    public function testCompletionAfterTheDoubleDashUsesTheNodeArguments()
    {
        $this->assertSame(['prod', 'staging'], $this->complete(['bin/console', 'deploy', '--', ''], 3));
        $this->assertSame(['api', 'web'], $this->complete(['bin/console', 'docker', 'compose', '--', ''], 4));
    }

    public function testCompletionKeepsUnrelatedLazyCommandsLazy()
    {
        $application = $this->createLazyApplication($instantiated);

        $this->complete(['bin/console', 'app:one', ''], 2, $application);
        $this->complete(['bin/console', 'app:one', '-'], 2, $application);

        $this->assertSame(['app:one'], $instantiated);
    }

    private function createLazyApplication(?array &$instantiated): Application
    {
        $instantiated = [];
        $factories = [];
        foreach (['app:one', 'app:two', 'other:sub'] as $name) {
            $factories[$name] = static function () use ($name, &$instantiated) {
                $instantiated[] = $name;

                return (new Command($name))->setCode(static fn () => 0);
            };
        }

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader(new FactoryCommandLoader($factories));

        return $application;
    }

    public function testTheHelpOfACommandKeepsUnrelatedLazyCommandsLazy()
    {
        $application = $this->createLazyApplication($instantiated);

        $application->run(new ArgvInput(['cli.php', 'app:one', '--help']), new NullOutput());

        $this->assertSame(['app:one'], $instantiated);
    }

    public function testHelpAcceptsASpacedPathThroughApplicationTester()
    {
        $application = $this->createTreeApplication();

        $tester = new ApplicationTester($application);
        $exitCode = $tester->run(['command' => 'help', 'command_name' => 'docker', 'compose']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('--file', $tester->getDisplay());
    }

    public function testANamedParameterTargetsTheNodeItself()
    {
        $application = $this->createTreeApplication();

        $tester = new ApplicationTester($application);
        $this->assertSame(0, $tester->run(['command' => 'deploy', 'target' => 'prod']));
        $this->assertSame('prod', $this->runs['deploy']->getArgument('target'));

        $this->assertSame(0, $tester->run(['command' => 'deploy', 'rollback']));
        $this->assertArrayHasKey('deploy:rollback', $this->runs);
    }

    public function testAPositionalParameterNamingNoSubCommandIsTheNodeArgument()
    {
        $application = $this->createTreeApplication();

        $tester = new ApplicationTester($application);
        $this->assertSame(0, $tester->run(['command' => 'deploy', 'prod']));
        $this->assertSame('prod', $this->runs['deploy']->getArgument('target'));

        $this->assertSame(0, $tester->run(['command' => 'deploy', '--', 'rollback']));
        $this->assertSame('rollback', $this->runs['deploy']->getArgument('target'));
        $this->assertArrayNotHasKey('deploy:rollback', $this->runs);
    }

    public function testAnImplicitRootDoesNotAppearInTheChain()
    {
        $application = $this->createTreeApplication();

        $observed = null;
        $application->get('ns:sub')->setCode(static function (CommandChain $chain) use (&$observed): int {
            $observed = [array_map(static fn ($c) => $c->getName(), $chain->getCommands()), $chain->getInput('ns')];

            return 0;
        });

        $application->run(new ArgvInput(['cli.php', 'ns', 'sub']), new NullOutput());

        $this->assertSame([['ns:sub'], null], $observed);
    }
}
