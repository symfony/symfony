<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Decode;

use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonEncoder\DataModel\DataAccessorInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\BackedEnumNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\DataModelNodeInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\ScalarNode;
use Symfony\Component\JsonEncoder\DataModel\FunctionDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\ScalarDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\VariableDataAccessor;
use Symfony\Component\JsonEncoder\Exception\RuntimeException;
use Symfony\Component\JsonEncoder\Exception\UnsupportedException;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * Generates and writes decoders PHP files.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class DecoderGenerator
{
    private ?PhpAstBuilder $phpAstBuilder = null;
    private ?PrettyPrinter $phpPrinter = null;
    private ?Filesystem $fs = null;

    public function __construct(
        private PropertyMetadataLoaderInterface $propertyMetadataLoader,
        private string $decodersDir,
    ) {
    }

    /**
     * Generates and writes a decoder PHP file and return its path.
     *
     * @param array<string, mixed> $options
     */
    public function generate(Type $type, bool $decodeFromStream, array $options = []): string
    {
        $path = $this->getPath($type, $decodeFromStream);
        if (is_file($path)) {
            return $path;
        }

        $this->phpAstBuilder ??= new PhpAstBuilder();
        $this->phpPrinter ??= new Standard(['phpVersion' => PhpVersion::fromComponents(8, 2)]);
        $this->fs ??= new Filesystem();

        $dataModel = $this->createDataModel($type, $options);
        $nodes = $this->phpAstBuilder->build($dataModel, $decodeFromStream, $options);
        $content = $this->phpPrinter->prettyPrintFile($nodes)."\n";

        if (!$this->fs->exists($this->decodersDir)) {
            $this->fs->mkdir($this->decodersDir);
        }

        $tmpFile = $this->fs->tempnam(\dirname($path), basename($path));

        try {
            $this->fs->dumpFile($tmpFile, $content);
            $this->fs->rename($tmpFile, $path);
            $this->fs->chmod($path, 0666 & ~umask());
        } catch (IOException $e) {
            throw new RuntimeException(\sprintf('Failed to write "%s" decoder file.', $path), previous: $e);
        }

        return $path;
    }

    private function getPath(Type $type, bool $decodeFromStream): string
    {
        return \sprintf('%s%s%s.json%s.php', $this->decodersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) $type), $decodeFromStream ? '.stream' : '');
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    public function createDataModel(Type $type, array $options = [], array $context = []): DataModelNodeInterface
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

        if ($type instanceof ObjectType && !$type instanceof EnumType) {
            $typeString = (string) $type;
            $className = $type->getClassName();

            if ($context['generated_classes'][$typeString] ??= false) {
                return ObjectNode::createGhost($type);
            }

            $propertiesNodes = [];
            $context['generated_classes'][$typeString] = true;

            $propertiesMetadata = $this->propertyMetadataLoader->load($className, $options, $context);

            foreach ($propertiesMetadata as $encodedName => $propertyMetadata) {
                $propertiesNodes[$encodedName] = [
                    'name' => $propertyMetadata->getName(),
                    'value' => $this->createDataModel($propertyMetadata->getType(), $options, $context),
                    'accessor' => function (DataAccessorInterface $accessor) use ($propertyMetadata): DataAccessorInterface {
                        foreach ($propertyMetadata->getDenormalizers() as $denormalizer) {
                            if (\is_string($denormalizer)) {
                                $denormalizerServiceAccessor = new FunctionDataAccessor('get', [new ScalarDataAccessor($denormalizer)], new VariableDataAccessor('denormalizers'));
                                $accessor = new FunctionDataAccessor('denormalize', [$accessor, new VariableDataAccessor('options')], $denormalizerServiceAccessor);

                                continue;
                            }

                            try {
                                $functionReflection = new \ReflectionFunction($denormalizer);
                            } catch (\ReflectionException $e) {
                                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                            }

                            $functionName = !$functionReflection->getClosureScopeClass()
                                ? $functionReflection->getName()
                                : \sprintf('%s::%s', $functionReflection->getClosureScopeClass()->getName(), $functionReflection->getName());
                            $arguments = $functionReflection->isUserDefined() ? [$accessor, new VariableDataAccessor('options')] : [$accessor];

                            $accessor = new FunctionDataAccessor($functionName, $arguments);
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
