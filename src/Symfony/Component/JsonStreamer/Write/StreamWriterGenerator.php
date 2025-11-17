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

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\JsonStreamer\DataModel\Write\BackedEnumNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Write\DataModelNodeInterface;
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
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
    private StreamerDumper $dumper;
    private ?PhpGenerator $phpGenerator = null;

    public function __construct(
        private PropertyMetadataLoaderInterface $propertyMetadataLoader,
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
        $path = \sprintf('%s%s%s.json.php', $this->streamWritersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) $type));
        $generateContent = function () use ($type, $options): string {
            $this->phpGenerator ??= new PhpGenerator();

            return $this->phpGenerator->generate($this->createDataModel($type, '$data', $options), $options);
        };

        $this->dumper->dump($type, $path, $generateContent);

        return $path;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    private function createDataModel(Type $type, string $accessor, array $options = [], array $context = []): DataModelNodeInterface
    {
        $context['original_type'] ??= $type;
        $context['depth'] ??= 0;

        if ($type instanceof UnionType) {
            return new CompositeNode($accessor, array_map(fn (Type $t): DataModelNodeInterface => $this->createDataModel($t, $accessor, $options, $context), $type->getTypes()));
        }

        if ($type instanceof BuiltinType) {
            return new ScalarNode($accessor, $type);
        }

        if ($type instanceof BackedEnumType) {
            return new BackedEnumNode($accessor, $type);
        }

        if ($type instanceof GenericType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType && !$type instanceof EnumType) {
            $typeString = (string) $type;
            $className = $type->getClassName();

            if ($context['generated_classes'][$typeString] ??= false) {
                return ObjectNode::createMock($accessor, $type);
            }

            $context['generated_classes'][$typeString] = true;
            $propertiesMetadata = $this->propertyMetadataLoader->load($className, $options, $context);

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
                        $valueTransformerServiceAccessor = "\$valueTransformers->get('$valueTransformer')";
                        $propertyAccessor = "{$valueTransformerServiceAccessor}->transform($propertyAccessor, ['_current_object' => $accessor] + \$options)";

                        continue;
                    }

                    try {
                        $functionReflection = new \ReflectionFunction($valueTransformer);
                    } catch (\ReflectionException $e) {
                        throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }

                    $functionName = !$functionReflection->getClosureCalledClass()
                        ? $functionReflection->getName()
                        : \sprintf('%s::%s', $functionReflection->getClosureCalledClass()->getName(), $functionReflection->getName());
                    $arguments = $functionReflection->isUserDefined() ? "$propertyAccessor, ['_current_object' => $accessor] + \$options" : $propertyAccessor;

                    $propertyAccessor = "$functionName($arguments)";
                }

                $propertiesNodes[$streamedName] = $this->createDataModel($propertyMetadata->getType(), $propertyAccessor, $options, $context);
            }

            return new ObjectNode($accessor, $type, $propertiesNodes);
        }

        if ($type instanceof CollectionType) {
            ++$context['depth'];

            return new CollectionNode(
                $accessor,
                $type,
                $this->createDataModel($type->getCollectionValueType(), '$value'.$context['depth'], $options, $context),
                $this->createDataModel($type->getCollectionKeyType(), '$key'.$context['depth'], $options, $context),
            );
        }

        throw new UnsupportedException(\sprintf('"%s" type is not supported.', (string) $type));
    }
}
