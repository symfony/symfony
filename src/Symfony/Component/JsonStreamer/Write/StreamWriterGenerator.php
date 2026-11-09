<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Write;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\JsonStreamer\DataModel\Write\BackedEnumNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Write\DataModelNodeInterface;
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;
use Symfony\Component\JsonStreamer\Exception\UnsupportedException;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\StreamerDumper;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * Generates and write stream writers PHP files.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class StreamWriterGenerator
{
    private const MAX_GENERIC_DEPTH = 512;
    private const MAX_GENERIC_TYPE_LENGTH = 65536;

    private StreamerDumper $dumper;
    private ?PhpGenerator $phpGenerator = null;

    public function __construct(
        private PropertyMetadataLoaderInterface $propertyMetadataLoader,
        private ContainerInterface $transformers,
        private string $streamWritersDir,
        ?ConfigCacheFactoryInterface $cacheFactory = null,
    ) {
        $this->dumper = new StreamerDumper($propertyMetadataLoader, $streamWritersDir, $cacheFactory);
    }

    /**
     * Generates and writes an stream writer PHP file and return its path.
     *
     * @param array<string, mixed> $options
     */
    public function generate(Type $type, array $options = []): string
    {
        $path = \sprintf('%s%s%s.%s.php', $this->streamWritersDir, \DIRECTORY_SEPARATOR, hash('xxh128', StreamerDumper::FORMAT_VERSION.$type), self::cacheVariant($options));
        $generateContent = function () use ($type, $options): string {
            $this->phpGenerator ??= new PhpGenerator($this->transformers);

            return $this->phpGenerator->generate($this->createDataModel($type, '$data', $options), $options);
        };

        $this->dumper->dump($type, $path, $generateContent);

        return $path;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function cacheVariant(array $options): string
    {
        $variant = $options['cache_variant'] ?? 'json';

        // the value ends up in the cache file name, so it must not reach the filesystem unchecked
        if (!\is_string($variant) || !preg_match('/^[a-zA-Z0-9_-]++$/', $variant)) {
            throw new InvalidArgumentException(\sprintf('The "cache_variant" option must match "[a-zA-Z0-9_-]+", "%s" given.', \is_string($variant) ? $variant : get_debug_type($variant)));
        }

        return $variant;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    private function createDataModel(Type $type, string $accessor, array $options = [], array &$context = []): DataModelNodeInterface
    {
        $context['original_type'] ??= $type;
        $context['depth'] ??= 0;

        if ($type instanceof IntersectionType) {
            throw new UnsupportedException(\sprintf('Intersection types are not supported ("%s").', (string) $type));
        }

        if ($type instanceof UnionType) {
            $nodes = [];
            foreach ($type->getTypes() as $t) {
                $nodes[] = $this->createDataModel($t, $accessor, $options, $context);
            }

            return new CompositeNode($accessor, $nodes);
        }

        if ($type instanceof BuiltinType) {
            return new ScalarNode($accessor, $type);
        }

        if ($type instanceof BackedEnumType) {
            return new BackedEnumNode($accessor, $type);
        }

        $genericType = null;

        if ($type instanceof GenericType) {
            $genericType = $type;
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType && !$type instanceof EnumType) {
            $typeString = (string) ($genericType ?? $type);
            $className = $type->getClassName();

            if ($context['generated_classes'][$typeString] ??= false) {
                return ObjectNode::createMock($accessor, $genericType ?? $type);
            }

            $context['generated_classes'][$typeString] = true;
            $propertiesMetadata = $this->propertyMetadataLoader->load($className, $options, $genericType ? ['original_type' => $genericType] + $context : $context);

            if ($genericType) {
                $context['generic_depths'][$className] ??= 0;

                // a class that contains itself with growing generic types would be expanded forever
                if (++$context['generic_depths'][$className] > self::MAX_GENERIC_DEPTH || \strlen($typeString) > self::MAX_GENERIC_TYPE_LENGTH) {
                    throw new UnsupportedException(\sprintf('"%s" contains itself with generic types that keep growing, which is not supported.', $className));
                }
            }

            try {
                $classReflection = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            $propertiesNodes = [];

            foreach ($propertiesMetadata as $streamedName => $propertyMetadata) {
                $propertyAccessor = $propertyMetadata->getName() ? $accessor.'->'.$propertyMetadata->getName() : 'null';

                foreach ($propertyMetadata->getValueTransformers() as $valueTransformer) {
                    if (\is_string($valueTransformer)) {
                        $valueTransformerServiceAccessor = '$transformers->get('.var_export($valueTransformer, true).')';

                        $propertyAccessor = "{$valueTransformerServiceAccessor}->transform($propertyAccessor, ['_current_object' => $accessor] + \$options)";

                        continue;
                    }

                    try {
                        $functionReflection = new \ReflectionFunction($valueTransformer);
                    } catch (\ReflectionException $e) {
                        throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }

                    if ($functionReflection->isAnonymous()) {
                        throw new RuntimeException(\sprintf('Cannot generate accessor for anonymous function "%s".', $functionReflection->getName()));
                    }

                    $functionName = !$functionReflection->getClosureCalledClass()
                        ? $functionReflection->getName()
                        : \sprintf('%s::%s', $functionReflection->getClosureCalledClass()->getName(), $functionReflection->getName());
                    $arguments = $functionReflection->isUserDefined() ? "$propertyAccessor, ['_current_object' => $accessor] + \$options" : $propertyAccessor;

                    $propertyAccessor = "$functionName($arguments)";
                }

                $propertiesNodes[$streamedName] = $this->createDataModel($propertyMetadata->getType(), $propertyAccessor, $options, $context);
            }

            if ($genericType) {
                --$context['generic_depths'][$className];
            }

            return new ObjectNode($accessor, $genericType ?? $type, $propertiesNodes);
        }

        if ($type instanceof CollectionType) {
            ++$context['depth'];
            $node = new CollectionNode(
                $accessor,
                $type,
                $this->createDataModel($type->getCollectionValueType(), '$value'.$context['depth'], $options, $context),
                $this->createDataModel($type->getCollectionKeyType(), '$key'.$context['depth'], $options, $context),
            );
            --$context['depth'];

            return $node;
        }

        throw new UnsupportedException(\sprintf('"%s" type is not supported.', (string) $type));
    }
}
