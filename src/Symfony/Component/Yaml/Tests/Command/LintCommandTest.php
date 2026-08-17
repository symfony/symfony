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
use Symfony\Component\Console\CI\GitlabCiReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Command\LintCommand;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;

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

    public function testLintIncorrectFileWithGitlabFormat()
    {
        if (!class_exists(GitlabCiReporter::class)) {
            $this->markTestSkipped('The "gitlab" format requires symfony/console 8.2 or higher.');
        }

        $incorrectContent = <<<YAML
            foo:
            bar
            YAML;
        $tester = $this->createCommandTester();
        $filename = $this->createFile($incorrectContent);

        $tester->execute(['filename' => $filename, '--format' => 'gitlab'], ['decorated' => false]);

        self::assertSame(1, $tester->getStatusCode(), 'Returns 1 in case of error');

        $report = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($report);
        self::assertCount(1, $report);
        self::assertSame('yaml-lint', $report[0]['check_name']);
        self::assertSame('major', $report[0]['severity']);
        self::assertStringContainsString('Unable to parse at line 2 (near "bar")', $report[0]['description']);
        self::assertSame($filename, $report[0]['location']['path']);
        self::assertSame(2, $report[0]['location']['lines']['begin']);
        self::assertNotEmpty($report[0]['fingerprint']);
    }

    public function testLintCorrectFileWithGitlabFormat()
    {
        if (!class_exists(GitlabCiReporter::class)) {
            $this->markTestSkipped('The "gitlab" format requires symfony/console 8.2 or higher.');
        }

        $tester = $this->createCommandTester();
        $filename = $this->createFile('foo: bar');

        $tester->execute(['filename' => $filename, '--format' => 'gitlab'], ['decorated' => false]);

        self::assertSame(0, $tester->getStatusCode(), 'Returns 0 in case of success');
        self::assertSame([], json_decode(trim($tester->getDisplay()), true));
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

    public function testLintValidatesAgainstSchemaOption()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("name: Symfony\nversion: 8");

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--check-schema' => $schema], ['decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml exits with code 0 when the content matches the schema');
        $this->assertStringContainsString('contain valid syntax and conform to the schema', $tester->getDisplay());
    }

    public function testLintReportsSchemaViolationWithOption()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile('version: not-an-integer');

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--check-schema' => $schema], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml exits with code 1 when the content violates the schema');
        $this->assertStringContainsString('ERROR', $tester->getDisplay());
    }

    public function testLintReportsAllSchemaViolations()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("name: 8\nversion: not-an-integer");

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--check-schema' => $schema], ['decorated' => false]);

        $this->assertSame(1, $ret);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('name:', $display);
        $this->assertStringContainsString('version:', $display);
    }

    public function testLintReportsUnresolvableSchemaAsError()
    {
        $this->skipIfJsonSchemaMissing();

        $filename = $this->createFile("name: Symfony\nversion: 8");

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--check-schema' => '/nonexistent/schema.json'], ['decorated' => false]);

        // An unresolvable schema is reported as a file error, it does not abort the command with a stack trace.
        $this->assertSame(1, $ret, 'lint:yaml exits with code 1 when the schema cannot be loaded');
        $this->assertStringContainsString('ERROR', $tester->getDisplay());
    }

    public function testLintValidatesAgainstSchemaHeader()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile(\sprintf("# yaml-language-server: \$schema=%s\nversion: not-an-integer", basename($schema)));

        $ret = $this->createCommandTester()->execute(['filename' => $filename, '--check-schema' => null], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml uses the schema declared in the file header');
    }

    public function testLintValidatesAgainstShortSchemaHeader()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile(\sprintf("# \$schema=%s\nversion: not-an-integer", basename($schema)));

        $ret = $this->createCommandTester()->execute(['filename' => $filename, '--check-schema' => null], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml uses the short schema header');
    }

    public function testLintDisplaysSchemaPerFileInVerboseMode()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("name: Symfony\nversion: 8");

        $tester = $this->createCommandTester();
        $tester->execute(['filename' => $filename, '--check-schema' => $schema], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        // The comment style wraps at 80 columns and prefixes every line with "//",
        // so collapse line breaks and the prefix before asserting on the label.
        $display = preg_replace('~\s*\n\s*(// )?~', ' ', $tester->getDisplay());
        $this->assertStringContainsString('validated against', $display);
    }

    public function testLintUsesTheInjectedSchemaResolver()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile('version: not-an-integer');

        $application = new Application();
        $resolver = $this->createStub(SchemaResolverInterface::class);
        $resolver->method('resolve')->willReturn($schema);

        $application->addCommand(new LintCommand(null, null, null, $resolver));
        $tester = new CommandTester($application->find('lint:yaml'));

        $ret = $tester->execute(['filename' => $filename, '--check-schema' => null], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml validates against the schema returned by the injected resolver');
    }

    public function testLintDisplaysSchemaRelativeToTheBaseDir()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("name: Symfony\nversion: 8");

        $application = new Application();
        $application->addCommand(new LintCommand(null, null, null, null, null, \dirname($schema)));
        $tester = new CommandTester($application->find('lint:yaml'));

        $tester->execute(['filename' => $filename, '--check-schema' => $schema], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $display = preg_replace('~\s*\n\s*(// )?~', ' ', $tester->getDisplay());
        $this->assertStringContainsString('validated against '.basename($schema), $display);
    }

    public function testLintReportsSchemaViolationLineWithGithubFormat()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("name: Symfony\nversion: not-an-integer");

        $tester = $this->createCommandTester();
        $tester->execute(['filename' => $filename, '--check-schema' => $schema, '--format' => 'github'], ['decorated' => false]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('line=2', $tester->getDisplay());
    }

    public function testLintDoesNotValidateSchemaWithoutOption()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile(\sprintf("# \$schema=%s\nversion: not-an-integer", basename($schema)));

        $ret = $this->createCommandTester()->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml only validates against a schema when --check-schema is used');
    }

    public function testLintWithoutSchemaOnlyChecksSyntax()
    {
        $filename = $this->createFile('version: not-an-integer');

        $ret = $this->createCommandTester()->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml only checks syntax when no schema is resolved');
    }

    public function testLintThrowsWhenSchemaOptionUsedWithoutLibrary()
    {
        if (class_exists(\Opis\JsonSchema\Validator::class)) {
            $this->markTestSkipped('The "opis/json-schema" package is installed.');
        }

        $filename = $this->createFile('foo: bar');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('opis/json-schema');

        $this->createCommandTester()->execute(['filename' => $filename, '--check-schema' => 'schema.json'], ['decorated' => false]);
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

    public function testComplete()
    {
        $tester = new CommandCompletionTester($this->createCommand());

        $expectedSuggestions = ['txt', 'json', 'github'];
        if (class_exists(GitlabCiReporter::class)) {
            $expectedSuggestions[] = 'gitlab';
        }

        $this->assertSame($expectedSuggestions, $tester->complete(['--format', '']));
    }

    private function createFile($content): string
    {
        $filename = tempnam(sys_get_temp_dir().'/framework-yml-lint-test', 'sf-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }

    private function createSchemaFile(): string
    {
        return $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'version' => ['type' => 'integer'],
            ],
        ]));
    }

    private function skipIfJsonSchemaMissing(): void
    {
        if (!class_exists(\Opis\JsonSchema\Validator::class)) {
            $this->markTestSkipped('The "opis/json-schema" package is required.');
        }
    }

    protected function createCommand(): Command
    {
        $application = new Application();
        $application->addCommand(new LintCommand());

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
