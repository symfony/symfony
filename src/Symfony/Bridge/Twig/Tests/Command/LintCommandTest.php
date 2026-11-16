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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Command\LintCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\DeprecatedCallableInfo;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class LintCommandTest extends TestCase
{
    private array $files;

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
     * When deprecations are not reported by the command, the testsuite reporter will catch them so we need to mark the test as ignoring deprecations.
     */
    #[IgnoreDeprecations]
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
        $this->assertMatchesRegularExpression('/DEPRECATION  in \S+ \(line 1\)/', trim($tester->getDisplay()));
        $this->assertStringContainsString('Filter "deprecated_filter" is deprecated', trim($tester->getDisplay()));
    }

    public function testLintFileWithMultipleReportedDeprecation()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile("{{ foo|deprecated_filter }}\n{{ bar|deprecated_filter }}");

        $ret = $tester->execute(['filename' => [$filename], '--show-deprecations' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(1, $ret, 'Returns 1 in case of error');
        $this->assertMatchesRegularExpression('/DEPRECATION  in \S+ \(line 1\)/', trim($tester->getDisplay()));
        $this->assertMatchesRegularExpression('/DEPRECATION  in \S+ \(line 2\)/', trim($tester->getDisplay()));
        $this->assertStringContainsString('Filter "deprecated_filter" is deprecated', trim($tester->getDisplay()));
    }

    #[Group('tty')]
    public function testLintDefaultPaths()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('OK in', trim($tester->getDisplay()));
    }

    public function testLintIncorrectFileWithMarkup()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile("{{ 'a' '</info>b' }}");

        $ret = $tester->execute(['filename' => [$filename]], ['decorated' => false]);

        $this->assertSame(1, $ret);
        $this->assertStringContainsString(">>  1      {{ 'a' '</info>b' }}", $tester->getDisplay());
        $this->assertStringContainsString('>> Unexpected token "string" of value "</info>b"', $tester->getDisplay());
    }

    public function testLintFilesWithMarkupInTheirName()
    {
        $cwd = getcwd();
        chdir(sys_get_temp_dir());

        try {
            $this->files[] = $valid = getcwd().'/sf-a<comment>.twig';
            $this->files[] = $invalid = getcwd().'/sf-b<comment>.twig';

            if (!@file_put_contents($valid, '{{ foo|deprecated_filter }}') || !@file_put_contents($invalid, '{{ foo')) {
                $this->markTestSkipped('The filesystem does not support "<" and ">" in file names.');
            }

            $tester = $this->createCommandTester();
            $tester->execute(['filename' => ['sf-a<comment>.twig', 'sf-b<comment>.twig'], '--show-deprecations' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

            $this->assertStringContainsString('DEPRECATION  in sf-a<comment>.twig (line 1)', $tester->getDisplay());
            $this->assertStringContainsString('is deprecated in sf-a<comment>.twig at line 1.', $tester->getDisplay());
            $this->assertStringContainsString('// OK in sf-a<comment>.twig', $tester->getDisplay());
            $this->assertStringContainsString('ERROR  in sf-b<comment>.twig (line 1)', $tester->getDisplay());
        } finally {
            chdir($cwd);
        }
    }

    public function testLintIncorrectFileWithJsonFormat()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile("{{ 'a' '<comment>b' }}");

        $ret = $tester->execute(['filename' => [$filename], '--format' => 'json'], ['decorated' => true]);
        $json = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(1, $ret);
        $this->assertStringContainsString('Unexpected token "string" of value "<comment>b"', $json[0]['message']);
    }

    public function testLintIncorrectFileWithInvalidUtf8WithJsonFormat()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile("{{ 'a' 'b\xFF' }}");

        $ret = $tester->execute(['filename' => [$filename], '--format' => 'json'], ['decorated' => false]);
        $json = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(1, $ret);
        $this->assertStringContainsString("Unexpected token \"string\" of value \"b\u{FFFD}\"", $json[0]['message']);
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

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = new CommandCompletionTester($this->createCommand());

        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'option' => [['--format', ''], ['txt', 'json', 'github']];
    }

    #[RequiresMethod(CompletionSuggestions::class, 'suggestPaths')]
    public function testCompleteFilenames()
    {
        $command = $this->createCommand();
        $input = CompletionInput::fromTokens(['lint:twig'], 1);
        $input->bind($command->getDefinition());
        $command->complete($input, $suggestions = new CompletionSuggestions());

        $this->assertSame('paths', $suggestions->getPathSuggestion());
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester($this->createCommand());
    }

    private function createCommand(): Command
    {
        $environment = new Environment(new FilesystemLoader(\dirname(__DIR__).'/Fixtures/templates/'));
        $options = ['deprecation_info' => new DeprecatedCallableInfo('foo/bar', '1.1')];
        $environment->addFilter(new TwigFilter('deprecated_filter', static fn ($v) => $v, $options));

        $command = new LintCommand($environment);

        $application = new Application();
        $application->addCommand($command);

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
