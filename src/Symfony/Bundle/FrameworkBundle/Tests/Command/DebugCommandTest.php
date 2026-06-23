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
use Symfony\Bundle\FrameworkBundle\Command\DebugCommand;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Bundle\FrameworkBundle\Debug\Section\AbstractDebugSection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

class DebugCommandTest extends TestCase
{
    public function testSingleMatchPrintsItsDetail()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute(['--container' => 'http_client']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Detail of http_client', $tester->getDisplay());
        // ANSI codes are stripped when the output is not decorated
        $this->assertStringNotContainsString("\e[", $tester->getDisplay());
    }

    public function testSingleMatchKeepsAnsiCodesWhenDecorated()
    {
        $tester = $this->createCommandTester();

        $tester->execute(['--container' => 'http_client'], ['decorated' => true]);

        $this->assertStringContainsString("\e[32mDetail of http_client\e[39m", $tester->getDisplay());
    }

    public function testMultipleMatchesAskToPickOne()
    {
        $tester = $this->createCommandTester();
        $tester->setInputs(['router.default']);

        $exitCode = $tester->execute(['--container' => 'r'], ['interactive' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Found 2 matching items', $tester->getDisplay());
        $this->assertStringContainsString('Detail of router.default', $tester->getDisplay());
    }

    public function testMultipleMatchesNonInteractiveListsThem()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute(['--container' => 'r'], ['interactive' => false, 'capture_stderr_separately' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame("router.default\nmailer.transports\n", $tester->getDisplay());
        $this->assertStringContainsString('Found 2 matching items', $tester->getErrorOutput());
    }

    public function testSectionOptionWithoutValueListsAllItems()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute(['--container' => null], ['interactive' => false, 'capture_stderr_separately' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame("http_client\nrouter.default\nmailer.transports\n", $tester->getDisplay());
    }

    public function testNoMatchesFailsWithSuggestions()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute(['--container' => 'http_clyent'], ['interactive' => false, 'capture_stderr_separately' => true]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('No items match "http_clyent"', $tester->getErrorOutput());
        $this->assertStringContainsString('http_client', $tester->getErrorOutput());
    }

    public function testPassingSeveralSectionOptionsIsInvalid()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute(['--container' => 'foo', '--routes' => 'bar'], ['capture_stderr_separately' => true]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('Pass only one section option at a time', $tester->getErrorOutput());
    }

    public function testBareQuerySearchesAllSections()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute(['query' => 'user'], ['interactive' => false, 'capture_stderr_separately' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame("Container › mailer.transports\nRoutes › user_show\nRoutes › user_edit\n", $tester->getDisplay());
    }

    public function testSectionOptionWithoutValueFallsBackToTheQueryArgument()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute(['query' => 'user_show', '--routes' => null]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Detail of user_show', $tester->getDisplay());
    }

    public function testBrokenSectionDoesNotPreventSearchingTheOthers()
    {
        $sections = [
            'container' => new StubDebugSection('Container', [new DebugItem('service', 'user_provider', 'user_provider')]),
            'broken' => new BrokenDebugSection(),
        ];
        $tester = new CommandTester(new DebugCommand($sections, ['container', 'broken']));

        $exitCode = $tester->execute(['query' => 'user'], ['interactive' => false, 'capture_stderr_separately' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        // the only match (from the healthy section) is printed directly
        $this->assertStringContainsString('Detail of user_provider', $tester->getDisplay());
        $this->assertStringContainsString('Searching the "Broken" section failed', $tester->getErrorOutput());
    }

    public function testNonInteractiveInputWithoutQueryFails()
    {
        $tester = $this->createCommandTester();

        $exitCode = $tester->execute([], ['interactive' => false, 'capture_stderr_separately' => true]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('interactive terminal', $tester->getErrorOutput());
    }

    public function testCompleteSuggestsSectionItemLabels()
    {
        $tester = new CommandCompletionTester($this->createCommand());

        $suggestions = $tester->complete(['--container', 'r']);

        $this->assertSame(['router.default', 'mailer.transports'], $suggestions);
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester($this->createCommand());
    }

    private function createCommand(): DebugCommand
    {
        $sections = [
            'container' => new StubDebugSection('Container', [
                new DebugItem('service', 'http_client', 'http_client'),
                new DebugItem('service', 'router.default', 'router.default'),
                new DebugItem('service', 'mailer.transports', 'mailer.transports', searchText: 'user'),
            ]),
            'routes' => new StubDebugSection('Routes', [
                new DebugItem('route', 'user_show', 'user_show'),
                new DebugItem('route', 'user_edit', 'user_edit'),
            ]),
        ];

        return new DebugCommand($sections, ['container', 'routes']);
    }
}

class StubDebugSection extends AbstractDebugSection
{
    /**
     * @param list<DebugItem> $items
     */
    public function __construct(
        private readonly string $label,
        private readonly array $items,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getShortLabel(): string
    {
        return $this->label;
    }

    public function describe(DebugItem $item, int $width): string
    {
        return \sprintf("\e[32mDetail of %s\e[39m (width %d)", $item->value, $width);
    }

    protected function buildItems(): array
    {
        return $this->items;
    }
}

class BrokenDebugSection extends AbstractDebugSection
{
    public function getLabel(): string
    {
        return 'Broken';
    }

    public function getShortLabel(): string
    {
        return 'Broken';
    }

    public function describe(DebugItem $item, int $width): string
    {
        throw new \RuntimeException('This section cannot describe anything.');
    }

    protected function buildItems(): array
    {
        throw new \RuntimeException('This section cannot load its items.');
    }
}
