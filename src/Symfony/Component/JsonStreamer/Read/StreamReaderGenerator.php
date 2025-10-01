<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Read;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonStreamer\DataModel\Read\BackedEnumNode;
use Symfony\Component\JsonStreamer\DataModel\Read\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Read\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Read\DataModelNodeInterface;
use Symfony\Component\JsonStreamer\DataModel\Read\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Read\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;
use Symfony\Component\JsonStreamer\Exception\UnsupportedException;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * Generates and writes stream readers PHP files.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class StreamReaderGenerator
{
    private ?PhpGenerator $phpGenerator = null;
    private ?Filesystem $fs = null;

    public function __construct(
        private PropertyMetadataLoaderInterface $propertyMetadataLoader,
        private string $streamReadersDir,
    ) {
    }

    /**
     * Generates and writes a stream reader PHP file and return its path.
     *
     * @param array<string, mixed> $options
     */
    public function generate(Type $type, bool $decodeFromStream, array $options = []): string
    {
        $path = $this->getPath($type, $decodeFromStream);
        if (is_file($path)) {
            return $path;
        }

        $this->phpGenerator ??= new PhpGenerator();
        $this->fs ??= new Filesystem();

        $dataModel = $this->createDataModel($type, $options);
        $php = $this->phpGenerator->generate($dataModel, $decodeFromStream, $options);

        if (!$this->fs->exists($this->streamReadersDir)) {
            $this->fs->mkdir($this->streamReadersDir);
        }

        $tmpFile = $this->fs->tempnam(\dirname($path), basename($path));

        try {
            $this->fs->dumpFile($tmpFile, $php);
            $this->fs->rename($tmpFile, $path);
            $this->fs->chmod($path, 0o666 & ~umask());
        } catch (IOException $e) {
            throw new RuntimeException(\sprintf('Failed to write "%s" stream reader file.', $path), previous: $e);
        }

        return $path;
    }

    private function getPath(Type $type, bool $decodeFromStream): string
    {
        return \sprintf('%s%s%s.json%s.php', $this->streamReadersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) $type), $decodeFromStream ? '.stream' : '');
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    private function createDataModel(Type $type, array $options = [], array $context = []): DataModelNodeInterface
    {
        $context['original_type'] ??= $type;

        if ($type instanceof UnionType) {
            return new CompositeNode(array_map(fn (Type $t): DataModelNodeInterface => $this->createDataModel($t, $options, $context), $type->getTypes()));
        }

        if ($type instanceof BuiltinType) {
            return new ScalarNode($type);
        }

        if ($type instanceof BackedEnumType) {
            return new BackedEnumNode($type);
        }

        if ($type instanceof GenericType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType && !$type instanceof EnumType) {
            $typeString = (string) $type;
            $className = $type->getClassName();

            if ($context['generated_classes'][$typeString] ??= false) {
                return ObjectNode::createMock($type);
            }

            $propertiesNodes = [];
            $context['generated_classes'][$typeString] = true;

            $propertiesMetadata = $this->propertyMetadataLoader->load($className, $options, $context);

            foreach ($propertiesMetadata as $streamedName => $propertyMetadata) {
                $propertiesNodes[$streamedName] = [
                    'name' => $propertyMetadata->getName(),
                    'value' => $this->createDataModel($propertyMetadata->getType(), $options, $context),
                    'accessor' => function (string $accessor) use ($propertyMetadata): string {
                        foreach ($propertyMetadata->getStreamToNativeValueTransformers() as $valueTransformer) {
                            if (\is_string($valueTransformer)) {
                                $accessor = "\$valueTransformers->get('$valueTransformer')->transform($accessor, \$options)";

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
                            $arguments = $functionReflection->isUserDefined() ? "$accessor, \$options" : $accessor;

                            $accessor = "$functionName($arguments)";
                        }

                        return $accessor;
                    },
                ];
            }

            return new ObjectNode($type, $propertiesNodes);
        }

        if ($type instanceof CollectionType) {
            return new CollectionNode($type, $this->createDataModel($type->getCollectionValueType(), $options, $context));
        }

        throw new UnsupportedException(\sprintf('"%s" type is not supported.', (string) $type));
    }
}
