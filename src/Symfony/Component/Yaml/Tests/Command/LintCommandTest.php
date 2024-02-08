<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Command\LintCommand;

/**
 * Tests the YamlLintCommand.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class LintCommandTest extends TestCase
{
    private array $files;

    public function testLintCorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('foo: bar');

        $ret = $tester->execute(['filename' => $filename], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertMatchesRegularExpression('/^\/\/ OK in /', trim($tester->getDisplay()));
    }

    public function testLintCorrectFiles()
    {
        $tester = $this->createCommandTester();
        $filename1 = $this->createFile('foo: bar');
        $filename2 = $this->createFile('bar: baz');

        $ret = $tester->execute(['filename' => [$filename1, $filename2]], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertMatchesRegularExpression('/^\/\/ OK in /', trim($tester->getDisplay()));
    }

    public function testLintIncorrectFile()
    {
        $incorrectContent = '
foo:
bar';
        $tester = $this->createCommandTester();
        $filename = $this->createFile($incorrectContent);

        $ret = $tester->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertEquals(1, $ret, 'Returns 1 in case of error');
        $this->assertStringContainsString('Unable to parse at line 3 (near "bar").', trim($tester->getDisplay()));
    }

    public function testLintIncorrectFileWithGithubFormat()
    {
        $incorrectContent = <<<YAML
foo:
bar
YAML;
        $tester = $this->createCommandTester();
        $filename = $this->createFile($incorrectContent);

        $tester->execute(['filename' => $filename, '--format' => 'github'], ['decorated' => false]);

        self::assertEquals(1, $tester->getStatusCode(), 'Returns 1 in case of error');
        self::assertStringMatchesFormat('%A::error file=%s,line=2,col=0::Unable to parse at line 2 (near "bar")%A', trim($tester->getDisplay()));
    }

    public function testLintAutodetectsGithubActionEnvironment()
    {
        $prev = getenv('GITHUB_ACTIONS');
        putenv('GITHUB_ACTIONS');

        try {
            putenv('GITHUB_ACTIONS=1');

            $incorrectContent = <<<YAML
foo:
bar
YAML;
            $tester = $this->createCommandTester();
            $filename = $this->createFile($incorrectContent);

            $tester->execute(['filename' => $filename], ['decorated' => false]);

            self::assertStringMatchesFormat('%A::error file=%s,line=2,col=0::Unable to parse at line 2 (near "bar")%A', trim($tester->getDisplay()));
        } finally {
            putenv('GITHUB_ACTIONS'.($prev ? "=$prev" : ''));
        }
    }

    public function testConstantAsKey()
    {
        $yaml = <<<YAML
!php/const 'Symfony\Component\Yaml\Tests\Command\Foo::TEST': bar
YAML;
        $ret = $this->createCommandTester()->execute(['filename' => $this->createFile($yaml)], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);
        $this->assertSame(0, $ret, 'lint:yaml exits with code 0 in case of success');
    }

    public function testCustomTags()
    {
        $yaml = <<<YAML
foo: !my_tag {foo: bar}
YAML;
        $ret = $this->createCommandTester()->execute(['filename' => $this->createFile($yaml), '--parse-tags' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);
        $this->assertSame(0, $ret, 'lint:yaml exits with code 0 in case of success');
    }

    public function testCustomTagsError()
    {
        $yaml = <<<YAML
foo: !my_tag {foo: bar}
YAML;
        $ret = $this->createCommandTester()->execute(['filename' => $this->createFile($yaml)], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);
        $this->assertSame(1, $ret, 'lint:yaml exits with code 1 in case of error');
    }

    public function testLintWithExclude()
    {
        $tester = $this->createCommandTester();
        $filename1 = $this->createFile('foo: bar');
        $filename2 = $this->createFile('bar: baz');

        $ret = $tester->execute(['filename' => [$filename1, $filename2], '--exclude' => [$filename1]], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);
        $this->assertSame(0, $ret, 'lint:yaml exits with code 0 in case of success');
        $this->assertStringContainsString('All 1 YAML files contain valid syntax.', trim($tester->getDisplay()));
    }

    public function testLintFileNotReadable()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('');
        unlink($filename);

        $this->expectException(\RuntimeException::class);

        $tester->execute(['filename' => $filename], ['decorated' => false]);
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

    private function createFile($content): string
    {
        $filename = tempnam(sys_get_temp_dir().'/framework-yml-lint-test', 'sf-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }

    protected function createCommand(): Command
    {
        $application = new Application();
        $application->add(new LintCommand());

        return $application->find('lint:yaml');
    }

    protected function createCommandTester(): CommandTester
    {
        return new CommandTester($this->createCommand());
    }

    protected function setUp(): void
    {
        $this->files = [];
        @mkdir(sys_get_temp_dir().'/framework-yml-lint-test');
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        @rmdir(sys_get_temp_dir().'/framework-yml-lint-test');
    }
}

class Foo
{
    public const TEST = 'foo';
}
