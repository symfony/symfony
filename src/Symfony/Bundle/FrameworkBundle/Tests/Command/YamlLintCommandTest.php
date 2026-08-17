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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\YamlLintCommand;
use Symfony\Bundle\FrameworkBundle\Command\YamlLintSchemaResolver;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Schema\FileHeaderSchemaResolver;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;

/**
 * Tests the YamlLintCommand.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class YamlLintCommandTest extends TestCase
{
    private array $files;

    public function testLintCorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('foo: bar');

        $tester->execute(
            ['filename' => $filename],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]
        );

        $tester->assertCommandIsSuccessful('Returns 0 in case of success');
        $this->assertStringContainsString('OK', trim($tester->getDisplay()));
    }

    public function testLintIncorrectFile()
    {
        $incorrectContent = '
foo:
bar';
        $tester = $this->createCommandTester();
        $filename = $this->createFile($incorrectContent);

        $tester->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertEquals(1, $tester->getStatusCode(), 'Returns 1 in case of error');
        $this->assertStringContainsString('Unable to parse at line 3 (near "bar").', trim($tester->getDisplay()));
    }

    public function testLintFileNotReadable()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('');
        unlink($filename);

        $this->expectException(\RuntimeException::class);

        $tester->execute(['filename' => $filename], ['decorated' => false]);
    }

    public function testGetHelp()
    {
        $command = new YamlLintCommand();
        $expected = <<<EOF
            Or find all files in a bundle:

              <info>php %command.full_name% @AcmeDemoBundle</info>
            EOF;

        $this->assertStringContainsString($expected, $command->getHelp());
    }

    public function testLintFilesFromBundleDirectory()
    {
        $tester = $this->createCommandTester($this->getKernelAwareApplicationMock());
        $tester->execute(
            ['filename' => '@AppBundle/Resources'],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]
        );

        $tester->assertCommandIsSuccessful('Returns 0 in case of success');
        $this->assertStringContainsString('[OK] All 0 YAML files contain valid syntax', trim($tester->getDisplay()));
    }

    public function testLintValidatesAgainstSchemaOption()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchema();

        $tester = $this->createCommandTester();
        $filename = $this->createFile('version: not-an-integer');

        $tester->execute(['filename' => $filename, '--check-schema' => $schema], ['decorated' => false]);

        $this->assertSame(1, $tester->getStatusCode(), 'lint:yaml validates against the schema given with --check-schema');
    }

    public function testLintValidatesAgainstSchemaHeader()
    {
        $this->skipIfJsonSchemaMissing();

        $schema = $this->createSchema();

        $tester = $this->createCommandTester();
        $filename = $this->createFile(\sprintf("# \$schema=%s\nversion: not-an-integer", basename($schema)));

        $tester->execute(['filename' => $filename, '--check-schema' => null], ['decorated' => false]);

        $this->assertSame(1, $tester->getStatusCode(), 'lint:yaml validates against the in-file schema header');
    }

    public function testLintValidatesPackageConfigAgainstDefaultConfigSchema()
    {
        $this->skipIfJsonSchemaMissing();

        $projectDir = sys_get_temp_dir().'/yml-lint-test';
        @mkdir($projectDir.'/config/packages', 0o777, true);
        file_put_contents($projectDir.'/config/schema.json', json_encode([
            'type' => 'object',
            'properties' => ['version' => ['type' => 'integer']],
        ]));
        $file = $projectDir.'/config/packages/framework.yaml';
        file_put_contents($file, 'version: not-an-integer');
        $this->files[] = $file;
        $this->files[] = $projectDir.'/config/schema.json';

        try {
            $tester = $this->createFrameworkCommandTester($projectDir);

            $tester->execute(['filename' => $file, '--check-schema' => null], ['decorated' => false]);

            $this->assertSame(1, $tester->getStatusCode(), 'config/packages files are validated against config/schema.json by default');
        } finally {
            @unlink($file);
            @unlink($projectDir.'/config/schema.json');
            @rmdir($projectDir.'/config/packages');
            @rmdir($projectDir.'/config');
        }
    }

    #[DataProvider('provideComponentSchemaFiles')]
    public function testLintValidatesWellKnownFilesAgainstComponentSchema(string $relativePath, string $schemaFile, ?string $requiredClass = null)
    {
        $this->skipIfJsonSchemaMissing();

        if (null !== $requiredClass && !class_exists($requiredClass)) {
            $this->markTestSkipped(\sprintf('The "%s" class is required.', $requiredClass));
        }

        $projectDir = sys_get_temp_dir().'/yml-lint-test';
        $file = $projectDir.'/'.$relativePath;
        @mkdir(\dirname($file), 0o777, true);
        // A sequence is not an object, which every component schema requires at the root.
        file_put_contents($file, '- invalid');
        $this->files[] = $file;

        try {
            $tester = $this->createFrameworkCommandTester($projectDir);

            $tester->execute(['filename' => $file, '--check-schema' => null], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]);

            $this->assertSame(1, $tester->getStatusCode(), \sprintf('%s is validated against the %s schema', $relativePath, $schemaFile));
            // Strip the wrap prefix and every whitespace so the wrapped absolute schema path stays searchable.
            $this->assertStringContainsString($schemaFile, preg_replace('/\s+/', '', preg_replace('~\n\s*// ~', '', $tester->getDisplay())));
        } finally {
            @unlink($file);
            @rmdir(\dirname($file));
            @rmdir($projectDir.'/config');
        }
    }

    public static function provideComponentSchemaFiles(): iterable
    {
        yield 'routes' => ['config/routes/app.yaml', 'routing.schema.json'];
        yield 'services' => ['config/services.yaml', 'services.schema.json'];
        yield 'serializer' => ['config/serializer/app.yaml', 'serialization.schema.json', \Symfony\Component\Serializer\Serializer::class];
        yield 'validator' => ['config/validator/app.yaml', 'validation.schema.json', \Symfony\Component\Validator\Validation::class];
    }

    public function testLintDoesNotApplyGeneratedSchemaOutsideConfigPackages()
    {
        $this->skipIfJsonSchemaMissing();

        $projectDir = sys_get_temp_dir().'/yml-lint-test';
        @mkdir($projectDir.'/config', 0o777, true);
        file_put_contents($projectDir.'/config/schema.json', json_encode(['type' => 'object']));
        $this->files[] = $projectDir.'/config/schema.json';
        // A file outside config/packages, which config/schema.json does not describe.
        $file = $this->createFile('- invalid');

        try {
            $tester = $this->createFrameworkCommandTester($projectDir);

            $tester->execute(['filename' => $file, '--check-schema' => null], ['decorated' => false]);

            $this->assertSame(0, $tester->getStatusCode(), 'config/schema.json is not applied outside config/packages');
        } finally {
            @unlink($projectDir.'/config/schema.json');
            @rmdir($projectDir.'/config');
        }
    }

    public function testLintDisplaysSchemaRelativeToProjectDir()
    {
        $this->skipIfJsonSchemaMissing();

        $projectDir = sys_get_temp_dir().'/yml-lint-test';
        @mkdir($projectDir.'/config', 0o777, true);
        $schema = $projectDir.'/config/schema.json';
        file_put_contents($schema, json_encode(['type' => 'object']));

        try {
            $tester = $this->createFrameworkCommandTester($projectDir);
            $filename = $this->createFile('foo: bar');

            $tester->execute(
                ['filename' => $filename, '--check-schema' => $schema],
                ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]
            );

            // The comment style wraps at 80 columns and prefixes every line with "//",
            // so collapse line breaks and the prefix before asserting on the label.
            $display = preg_replace('~\s*\n\s*(// )?~', ' ', $tester->getDisplay());
            $this->assertStringContainsString('validated against config/schema.json', $display);
        } finally {
            @unlink($schema);
            @rmdir($projectDir.'/config');
        }
    }

    private function skipIfJsonSchemaMissing(): void
    {
        if (!interface_exists(SchemaResolverInterface::class)) {
            $this->markTestSkipped('symfony/yaml 8.2 or higher is required.');
        }

        if (!class_exists(\Opis\JsonSchema\Validator::class)) {
            $this->markTestSkipped('The "opis/json-schema" package is required.');
        }
    }

    private function createSchema(): string
    {
        return $this->createFile(json_encode([
            'type' => 'object',
            'properties' => ['version' => ['type' => 'integer']],
        ]));
    }

    private function createFrameworkCommandTester(string $projectDir): CommandTester
    {
        $application = new BaseApplication();
        $application->addCommand(new YamlLintCommand(new YamlLintSchemaResolver($projectDir.'/config', new FileHeaderSchemaResolver()), null, $projectDir));

        return $this->createCommandTester($application);
    }

    private function createFile($content): string
    {
        $filename = tempnam(sys_get_temp_dir().'/yml-lint-test', 'sf-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }

    private function createCommandTester($application = null): CommandTester
    {
        if (!$application) {
            $application = new BaseApplication();
            $application->addCommand(new YamlLintCommand());
        }

        $command = $application->find('lint:yaml');

        $command->setApplication($application);

        return new CommandTester($command);
    }

    private function getKernelAwareApplicationMock()
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->expects($this->once())
            ->method('locateResource')
            ->with('@AppBundle/Resources')
            ->willReturn(sys_get_temp_dir().'/yml-lint-test');

        $application = $this->createMock(Application::class);
        $application
            ->expects($this->once())
            ->method('getKernel')
            ->willReturn($kernel);

        $application
            ->expects($this->once())
            ->method('getHelperSet')
            ->willReturn(new HelperSet());

        $application
            ->method('getDefinition')
            ->willReturn(new InputDefinition());

        $application
            ->expects($this->once())
            ->method('find')
            ->with('lint:yaml')
            ->willReturn(new YamlLintCommand());

        return $application;
    }

    protected function setUp(): void
    {
        @mkdir(sys_get_temp_dir().'/yml-lint-test');
        $this->files = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        @rmdir(sys_get_temp_dir().'/yml-lint-test');
    }
}
