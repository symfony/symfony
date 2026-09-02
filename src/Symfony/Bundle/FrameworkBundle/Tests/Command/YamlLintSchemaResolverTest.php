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
use Symfony\Bundle\FrameworkBundle\Command\YamlLintSchemaResolver;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;

class YamlLintSchemaResolverTest extends TestCase
{
    private string $projectDir;
    private string $configDir;

    protected function setUp(): void
    {
        $projectDir = sys_get_temp_dir().'/yaml-lint-schema-resolver';
        @mkdir($projectDir.'/config/packages', 0o777, true);

        // Canonicalize, since the resolver compares the file with the resolved config directory.
        $this->projectDir = realpath($projectDir);
        $this->configDir = $this->projectDir.'/config';

        // Skip last: tearDown() runs for skipped tests too, so the properties must be set.
        if (!interface_exists(SchemaResolverInterface::class)) {
            $this->markTestSkipped('symfony/yaml 8.2 or higher is required.');
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->configDir.'/schema.json');
        @rmdir($this->configDir.'/packages');
        @rmdir($this->configDir);
        @rmdir($this->projectDir);
    }

    public function testStdinHasNoSchema()
    {
        $resolver = new YamlLintSchemaResolver($this->configDir);

        $this->assertNull($resolver->resolve(''));
    }

    public function testTheDecoratedResolverComesFirst()
    {
        $header = $this->createStub(SchemaResolverInterface::class);
        $header->method('resolve')->willReturn('/from-the-document.json');

        $resolver = new YamlLintSchemaResolver($this->configDir, $header);

        // A file that would otherwise resolve to the routing schema.
        $this->assertSame('/from-the-document.json', $resolver->resolve('# $schema=/from-the-document.json', $this->projectDir.'/config/routes.yaml'));
    }

    public function testConventionsApplyWhenTheDecoratedResolverFindsNothing()
    {
        $header = $this->createStub(SchemaResolverInterface::class);
        $header->method('resolve')->willReturn(null);

        $resolver = new YamlLintSchemaResolver($this->configDir, $header);

        $this->assertSame('routing.schema.json', basename($resolver->resolve('version: 8', $this->projectDir.'/config/routes.yaml')));
    }

    #[DataProvider('provideWellKnownFiles')]
    public function testWellKnownFilesUseTheComponentSchema(string $relativePath, string $schemaFile, ?string $requiredClass = null)
    {
        if (null !== $requiredClass && !class_exists($requiredClass)) {
            $this->markTestSkipped(\sprintf('The "%s" class is required.', $requiredClass));
        }

        $resolver = new YamlLintSchemaResolver($this->configDir);

        $schema = $resolver->resolve('', $this->projectDir.'/'.$relativePath);

        $this->assertNotNull($schema, \sprintf('"%s" resolves to a schema.', $relativePath));
        $this->assertSame($schemaFile, basename($schema));
        $this->assertFileExists($schema);
    }

    public static function provideWellKnownFiles(): iterable
    {
        yield 'routes' => ['config/routes.yaml', 'routing.schema.json'];
        yield 'routes in a subdirectory' => ['config/routes/app.yaml', 'routing.schema.json'];
        yield 'services' => ['config/services.yaml', 'services.schema.json'];
        yield 'services per environment' => ['config/services_test.yaml', 'services.schema.json'];
        yield 'serializer' => ['config/serializer/app.yaml', 'serialization.schema.json', Serializer::class];
        yield 'validator' => ['config/validator/app.yaml', 'validation.schema.json', Validation::class];
        yield 'the .yml extension' => ['config/routes.yml', 'routing.schema.json'];
        yield 'services with the .yml extension' => ['config/services_test.yml', 'services.schema.json'];
    }

    #[DataProvider('provideUnknownFiles')]
    public function testUnknownFilesHaveNoSchema(string $relativePath)
    {
        $resolver = new YamlLintSchemaResolver($this->configDir);

        $this->assertNull($resolver->resolve('', $this->projectDir.'/'.$relativePath));
    }

    public static function provideUnknownFiles(): iterable
    {
        yield 'an arbitrary file' => ['translations/messages.en.yaml'];
        yield 'another extension' => ['config/routes.xml'];
        yield 'a file outside the config directory' => ['src/routes.yaml'];
        yield 'a subdirectory of config' => ['config/other/app.yaml'];
        // "config/services_test.yaml" matches, but the pattern must not cross directories.
        yield 'a nested services file' => ['config/services/app.yaml'];
    }

    public function testWindowsPathsAreNormalized()
    {
        $resolver = new YamlLintSchemaResolver($this->configDir);

        $schema = $resolver->resolve('', 'C:\\project\\config\\routes.yaml');

        $this->assertNotNull($schema);
        $this->assertSame('routing.schema.json', basename($schema));
    }

    public function testPackageConfigUsesTheGeneratedSchema()
    {
        touch($this->configDir.'/schema.json');

        $resolver = new YamlLintSchemaResolver($this->configDir);

        $this->assertSame($this->configDir.'/schema.json', $resolver->resolve('', $this->configDir.'/packages/framework.yaml'));
        $this->assertSame($this->configDir.'/schema.json', $resolver->resolve('', $this->configDir.'/packages/dev/monolog.yaml'));
        $this->assertSame($this->configDir.'/schema.json', $resolver->resolve('', $this->configDir.'/packages/twig.yml'));
    }

    public function testPackageConfigHasNoSchemaWithoutTheGeneratedFile()
    {
        $resolver = new YamlLintSchemaResolver($this->configDir);

        $this->assertNull($resolver->resolve('', $this->configDir.'/packages/framework.yaml'));
    }

    public function testTheGeneratedSchemaOnlyAppliesToPackageConfig()
    {
        touch($this->configDir.'/schema.json');

        $resolver = new YamlLintSchemaResolver($this->configDir);

        $this->assertNull($resolver->resolve('', $this->configDir.'/packages.yaml'));
        $this->assertNull($resolver->resolve('', $this->projectDir.'/other.yaml'));
        // A packages directory of another project, or of a bundle, is not described by this schema.
        $this->assertNull($resolver->resolve('', '/elsewhere/config/packages/framework.yaml'));
        // A file explicitly passed to the command may have any extension.
        $this->assertNull($resolver->resolve('', $this->configDir.'/packages/framework.xml'));
    }
}
