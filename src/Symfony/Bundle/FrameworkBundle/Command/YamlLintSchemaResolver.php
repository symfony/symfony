<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;

/**
 * Resolves the default JSON Schema of a YAML file, based on Symfony conventions.
 *
 * A schema declared by the document itself, through the decorated resolver, comes first.
 * Then well-known config files (routes, services, serializer and validator mappings) validate
 * against their component schema, wherever they live, since the naming convention also applies
 * inside bundles. Finally, the bundle configuration files of this project (the packages directory
 * of its config directory) fall back to the generated schema.json when it exists. Any other file
 * has no schema.
 *
 * Without a config directory, the generated schema is never applied.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class YamlLintSchemaResolver implements SchemaResolverInterface
{
    public function __construct(
        private readonly ?string $configDir = null,
        private readonly ?SchemaResolverInterface $schemaResolver = null,
    ) {
    }

    public function resolve(string $content, ?string $file = null): ?string
    {
        if ($schema = $this->schemaResolver?->resolve($content, $file)) {
            return $schema;
        }

        if (!$file) {
            return null;
        }

        $file = self::canonicalize($file);
        foreach (self::getComponentSchemas() as [$pattern, $class, $schema]) {
            if (preg_match($pattern, $file) && class_exists($class)) {
                return \dirname((new \ReflectionClass($class))->getFileName()).$schema;
            }
        }

        if ($this->configDir && preg_match('#\.ya?ml$#', $file) && is_file($schema = $this->configDir.'/schema.json')) {
            return str_starts_with($file, self::canonicalize($this->configDir).'/packages/') ? $schema : null;
        }

        return null;
    }

    private static function canonicalize(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }

    /**
     * @return iterable<array{0: string, 1: class-string, 2: string}> A pattern, a component class and the schema path relative to it
     */
    private static function getComponentSchemas(): iterable
    {
        yield ['#(^|/)config/routes(\.ya?ml|/.+\.ya?ml)$#', Route::class, '/Loader/schema/routing.schema.json'];
        yield ['#(^|/)config/services(\.ya?ml|_[^/]+\.ya?ml)$#', ContainerBuilder::class, '/Loader/schema/services.schema.json'];
        yield ['#(^|/)config/serializer/.+\.ya?ml$#', Serializer::class, '/Mapping/Loader/schema/serialization.schema.json'];
        yield ['#(^|/)config/validator/.+\.ya?ml$#', Validation::class, '/Mapping/Loader/schema/validation.schema.json'];
    }
}
