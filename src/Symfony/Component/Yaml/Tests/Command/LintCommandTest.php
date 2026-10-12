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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CI\GitlabCiReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Command\LintCommand;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;
use Symfony\Component\Yaml\Schema\SchemaValidatorInterface;

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
        $ret = $tester->execute(['filename' => $filename, '--schema' => $schema], ['decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml exits with code 0 when the content matches the schema');
        $this->assertStringContainsString('contain valid syntax and conform to the schema', $tester->getDisplay());
    }

    public function testLintReportsSchemaViolationWithOption()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile('version: not-an-integer');

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--schema' => $schema], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml exits with code 1 when the content violates the schema');
        $this->assertStringContainsString('ERROR', $tester->getDisplay());
    }

    public function testLintValidatesMappingsAsObjectsAndSequencesAsArrays()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'map' => ['type' => 'object'],
                'list' => ['type' => 'array'],
            ],
        ]));

        $tester = $this->createCommandTester();

        $this->assertSame(0, $tester->execute(['filename' => $this->createFile("map: {}\nlist: []"), '--schema' => $schema], ['decorated' => false]), 'An empty mapping is an object and an empty sequence is an array');
        $this->assertSame(0, $tester->execute(['filename' => $this->createFile("map: {0: a, 1: b}\nlist: [a, b]"), '--schema' => $schema], ['decorated' => false]), 'A mapping with integer keys is an object');
        $this->assertSame(1, $tester->execute(['filename' => $this->createFile('list: {}'), '--schema' => $schema], ['decorated' => false]), 'An empty mapping is not an array');
        $this->assertSame(1, $tester->execute(['filename' => $this->createFile('map: []'), '--schema' => $schema], ['decorated' => false]), 'An empty sequence is not an object');
    }

    public function testLintReportsAllSchemaViolations()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("name: 8\nversion: not-an-integer");

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--schema' => $schema], ['decorated' => false]);

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
        $ret = $tester->execute(['filename' => $filename, '--schema' => '/nonexistent/schema.json'], ['decorated' => false]);

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
        $tester->execute(['filename' => $filename, '--schema' => $schema], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

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

        $tester->execute(['filename' => $filename, '--schema' => $schema], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $display = preg_replace('~\s*\n\s*(// )?~', ' ', $tester->getDisplay());
        $this->assertStringContainsString('validated against '.basename($schema), $display);
    }

    public function testLintReportsSchemaViolationLineWithGithubFormat()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("name: Symfony\nversion: not-an-integer");

        $tester = $this->createCommandTester();
        $tester->execute(['filename' => $filename, '--schema' => $schema, '--format' => 'github'], ['decorated' => false]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('line=2', $tester->getDisplay());
    }

    public function testLintValidatesAgainstSchemaHeaderByDefault()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile(\sprintf("# \$schema=%s\nversion: not-an-integer", basename($schema)));

        $ret = $this->createCommandTester()->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml validates against the schema of the file without --check-schema');
    }

    public function testLintReportsSchemaConformanceByDefault()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile(\sprintf("# \$schema=%s\nversion: 8", basename($schema)));

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertSame(0, $ret);
        $this->assertStringContainsString('All 1 YAML files contain valid syntax and conform to the schema.', $tester->getDisplay());
    }

    public function testLintDoesNotValidateSchemaWithNoCheckSchemaOption()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile(\sprintf("# \$schema=%s\nversion: not-an-integer", basename($schema)));

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--no-check-schema' => true], ['decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml only checks syntax with --no-check-schema');
        $this->assertStringContainsString('All 1 YAML files contain valid syntax.', $tester->getDisplay());
    }

    public function testLintThrowsWhenSchemaAndNoCheckSchemaAreCombined()
    {
        $this->skipIfJsonSchemaMissing();

        $filename = $this->createFile('foo: bar');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--schema" option cannot be used with "--no-check-schema".');

        $this->createCommandTester()->execute(['filename' => $filename, '--schema' => $this->createSchemaFile(), '--no-check-schema' => true], ['decorated' => false]);
    }

    public function testCheckSchemaDefaultsToWhetherTheValidatorIsSupported()
    {
        $this->assertTrue((new LintCommand(null, null, null, null, $this->createValidator(true)))->getDefinition()->getOption('check-schema')->getDefault());
        $this->assertFalse((new LintCommand(null, null, null, null, $this->createValidator(false)))->getDefinition()->getOption('check-schema')->getDefault());
    }

    public function testHelpDescribesTheSchemaValidationAsConfigured()
    {
        $help = (new LintCommand(null, null, null, null, $this->createValidator(true)))->getHelp();
        $this->assertStringContainsString('The content of each file is also validated against its JSON Schema', $help);
        $this->assertStringNotContainsString('opis/json-schema', $help);

        $help = (new LintCommand(null, null, null, null, $this->createValidator(false)))->getHelp();
        $this->assertStringContainsString('Install the "opis/json-schema" package to also validate the content of each', $help);
        $this->assertStringNotContainsString('The content of each file is also validated', $help);
    }

    #[DataProvider('provideOptionsRequiringAValidator')]
    public function testLintThrowsWhenAnOptionRequiresAValidatorThatIsNotSupported(array $options)
    {
        $application = new Application();
        $application->addCommand(new LintCommand(null, null, null, null, $this->createValidator(false)));
        $tester = new CommandTester($application->find('lint:yaml'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('opis/json-schema');

        $tester->execute(['filename' => $this->createFile('foo: bar')] + $options, ['decorated' => false]);
    }

    public static function provideOptionsRequiringAValidator(): iterable
    {
        yield '--check-schema' => [['--check-schema' => true]];
        yield '--schema' => [['--schema' => 'schema.json']];
    }

    public function testLintResolvesRelativeSchemaHeaderAgainstTheFile()
    {
        $this->skipIfJsonSchemaMissing();

        $this->createSchemaFile(sys_get_temp_dir().'/framework-yml-lint-test/schema.json');
        $filename = $this->createPackageFile("# yaml-language-server: \$schema=../schema.json\nversion: not-an-integer");

        $ret = $this->createCommandTester()->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml resolves a relative schema against the directory of the file');
    }

    public function testLintSkipsMissingSchemaByDefault()
    {
        $this->skipIfJsonSchemaMissing();

        $filename = $this->createPackageFile("# yaml-language-server: \$schema=../schema.json\nversion: not-an-integer");

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml skips a schema that does not exist when --check-schema is not used');
        $this->assertStringContainsString('All 1 YAML files contain valid syntax.', $tester->getDisplay());
        // Strip the wrap prefix and every whitespace so the wrapped absolute schema path stays searchable.
        $this->assertStringContainsString('/packages/../schema.jsonnotavailable)', preg_replace('/\s+/', '', preg_replace('~\n\s*// ~', '', $tester->getDisplay())));
    }

    public function testLintReportsMissingSchemaWithOption()
    {
        $this->skipIfJsonSchemaMissing();

        $filename = $this->createPackageFile("# yaml-language-server: \$schema=../schema.json\nversion: 8");

        $tester = $this->createCommandTester();
        $ret = $tester->execute(['filename' => $filename, '--check-schema' => null], ['decorated' => false]);

        $this->assertSame(1, $ret, 'lint:yaml reports a schema that does not exist when --check-schema is used');
        $this->assertStringContainsString('../schema.json', $tester->getDisplay());
    }

    public function testLintDoesNotValidateEmptyDocumentAgainstSchema()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchemaFile();
        $filename = $this->createFile("# version: 8\n");

        $ret = $this->createCommandTester()->execute(['filename' => $filename, '--schema' => $schema], ['decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml does not validate a document without content against a schema');
    }

    public function testLintSkipsRemoteSchemaByDefault()
    {
        $this->skipIfJsonSchemaMissing();

        $filename = $this->createFile("# yaml-language-server: \$schema=https://example.com/schema.json\nversion: 8");

        $ret = $this->createCommandTester()->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertSame(0, $ret, 'lint:yaml skips a remote schema when --check-schema is not used');
    }

    public function testLintOnlyChecksSyntaxByDefaultWhenNoValidatorIsSupported()
    {
        $schema = $this->createSchemaFile();
        $filename = $this->createFile(\sprintf("# \$schema=%s\nversion: not-an-integer", basename($schema)));

        $validator = $this->createMock(SchemaValidatorInterface::class);
        $validator->method('isSupported')->willReturn(false);
        $validator->expects($this->never())->method('validate');

        $application = new Application();
        $application->addCommand(new LintCommand(null, null, null, null, $validator));
        $tester = new CommandTester($application->find('lint:yaml'));

        $ret = $tester->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertSame(0, $ret);
        $this->assertStringContainsString('All 1 YAML files contain valid syntax.', $tester->getDisplay());
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

        $this->createCommandTester()->execute(['filename' => $filename, '--schema' => 'schema.json'], ['decorated' => false]);
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

    private function createPackageFile(string $content): string
    {
        @mkdir(sys_get_temp_dir().'/framework-yml-lint-test/packages');
        $filename = tempnam(sys_get_temp_dir().'/framework-yml-lint-test/packages', 'sf-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }

    private function createSchemaFile(?string $filename = null): string
    {
        $schema = json_encode([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'version' => ['type' => 'integer'],
            ],
        ]);

        if (null === $filename) {
            return $this->createFile($schema);
        }

        file_put_contents($filename, $schema);
        $this->files[] = $filename;

        return $filename;
    }

    private function createValidator(bool $supported): SchemaValidatorInterface
    {
        $validator = $this->createStub(SchemaValidatorInterface::class);
        $validator->method('isSupported')->willReturn($supported);

        return $validator;
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

        @rmdir(sys_get_temp_dir().'/framework-yml-lint-test/packages');
        @rmdir(sys_get_temp_dir().'/framework-yml-lint-test');
    }
}

class Foo
{
    public const TEST = 'foo';
}
