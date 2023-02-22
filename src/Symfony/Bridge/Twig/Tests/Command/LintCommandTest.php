<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Command\LintCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class LintCommandTest extends TestCase
{
    private $files;

    public function testLintCorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('{{ foo }}');

        $ret = $tester->execute(['filename' => [$filename]], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('OK in', trim($tester->getDisplay()));
    }

    public function testLintIncorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('{{ foo');

        $ret = $tester->execute(['filename' => [$filename]], ['decorated' => false]);

        $this->assertEquals(1, $ret, 'Returns 1 in case of error');
        $this->assertMatchesRegularExpression('/ERROR  in \S+ \(line /', trim($tester->getDisplay()));
    }

    public function testLintFileNotReadable()
    {
        $this->expectException(\RuntimeException::class);
        $tester = $this->createCommandTester();
        $filename = $this->createFile('');
        unlink($filename);

        $tester->execute(['filename' => [$filename]], ['decorated' => false]);
    }

    public function testLintFileCompileTimeException()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile("{{ 2|number_format(2, decimal_point='.', ',') }}");

        $ret = $tester->execute(['filename' => [$filename]], ['decorated' => false]);

        $this->assertEquals(1, $ret, 'Returns 1 in case of error');
        $this->assertMatchesRegularExpression('/ERROR  in \S+ \(line /', trim($tester->getDisplay()));
    }

    /**
     * When deprecations are not reported by the command, the testsuite reporter will catch them so we need to mark the test as legacy.
     *
     * @group legacy
     */
    public function testLintFileWithNotReportedDeprecation()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('{{ foo|deprecated_filter }}');

        $ret = $tester->execute(['filename' => [$filename]], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('OK in', trim($tester->getDisplay()));
    }

    public function testLintFileWithReportedDeprecation()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('{{ foo|deprecated_filter }}');

        $ret = $tester->execute(['filename' => [$filename], '--show-deprecations' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(1, $ret, 'Returns 1 in case of error');
        $this->assertMatchesRegularExpression('/ERROR  in \S+ \(line 1\)/', trim($tester->getDisplay()));
        $this->assertStringContainsString('Filter "deprecated_filter" is deprecated', trim($tester->getDisplay()));
    }

    /**
     * @group tty
     */
    public function testLintDefaultPaths()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('OK in', trim($tester->getDisplay()));
    }

    public function testLintIncorrectFileWithGithubFormat()
    {
        $filename = $this->createFile('{{ foo');
        $tester = $this->createCommandTester();
        $tester->execute(['filename' => [$filename], '--format' => 'github'], ['decorated' => false]);
        self::assertEquals(1, $tester->getStatusCode(), 'Returns 1 in case of error');
        self::assertStringMatchesFormat('%A::error file=%s,line=1,col=0::Unexpected token "end of template" ("end of print statement" expected).%A', trim($tester->getDisplay()));
    }

    public function testLintAutodetectsGithubActionEnvironment()
    {
        $prev = getenv('GITHUB_ACTIONS');
        putenv('GITHUB_ACTIONS');

        try {
            putenv('GITHUB_ACTIONS=1');

            $filename = $this->createFile('{{ foo');
            $tester = $this->createCommandTester();

            $tester->execute(['filename' => [$filename]], ['decorated' => false]);
            self::assertStringMatchesFormat('%A::error file=%s,line=1,col=0::Unexpected token "end of template" ("end of print statement" expected).%A', trim($tester->getDisplay()));
        } finally {
            putenv('GITHUB_ACTIONS'.($prev ? "=$prev" : ''));
        }
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = new CommandCompletionTester($this->createCommand());

        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public static function provideCompletionSuggestions()
    {
        yield 'option' => [['--format', ''], ['txt', 'json', 'github']];
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester($this->createCommand());
    }

    private function createCommand(): Command
    {
        $environment = new Environment(new FilesystemLoader(\dirname(__DIR__).'/Fixtures/templates/'));
        $environment->addFilter(new TwigFilter('deprecated_filter', fn ($v) => $v, ['deprecated' => true]));

        $command = new LintCommand($environment);

        $application = new Application();
        $application->add($command);

        return $application->find('lint:twig');
    }

    private function createFile($content): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'sf-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }

    protected function setUp(): void
    {
        $this->files = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
