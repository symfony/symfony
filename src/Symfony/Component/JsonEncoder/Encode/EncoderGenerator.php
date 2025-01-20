<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Encode;

use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonEncoder\DataModel\DataAccessorInterface;
use Symfony\Component\JsonEncoder\DataModel\Encode\BackedEnumNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\DataModelNodeInterface;
use Symfony\Component\JsonEncoder\DataModel\Encode\ExceptionNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\ScalarNode;
use Symfony\Component\JsonEncoder\DataModel\FunctionDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\PropertyDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\ScalarDataAccessor;
use Symfony\Component\JsonEncoder\DataModel\VariableDataAccessor;
use Symfony\Component\JsonEncoder\Exception\MaxDepthException;
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
 * Generates and write encoders PHP files.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class EncoderGenerator
{
    private const MAX_DEPTH = 512;

    private ?PhpAstBuilder $phpAstBuilder = null;
    private ?PhpOptimizer $phpOptimizer = null;
    private ?PrettyPrinter $phpPrinter = null;
    private ?Filesystem $fs = null;

    public function __construct(
        private PropertyMetadataLoaderInterface $propertyMetadataLoader,
        private string $encodersDir,
    ) {
    }

    /**
     * Generates and writes an encoder PHP file and return its path.
     *
     * @param array<string, mixed> $options
     */
    public function generate(Type $type, array $options = []): string
    {
        $path = $this->getPath($type);
        if (is_file($path)) {
            return $path;
        }

        $this->phpAstBuilder ??= new PhpAstBuilder();
        $this->phpOptimizer ??= new PhpOptimizer();
        $this->phpPrinter ??= new Standard(['phpVersion' => PhpVersion::fromComponents(8, 2)]);
        $this->fs ??= new Filesystem();

        $dataModel = $this->createDataModel($type, new VariableDataAccessor('data'), $options);

        $nodes = $this->phpAstBuilder->build($dataModel, $options);
        $nodes = $this->phpOptimizer->optimize($nodes);

        $content = $this->phpPrinter->prettyPrintFile($nodes)."\n";

        if (!$this->fs->exists($this->encodersDir)) {
            $this->fs->mkdir($this->encodersDir);
        }

        $tmpFile = $this->fs->tempnam(\dirname($path), basename($path));

        try {
            $this->fs->dumpFile($tmpFile, $content);
            $this->fs->rename($tmpFile, $path);
            $this->fs->chmod($path, 0666 & ~umask());
        } catch (IOException $e) {
            throw new RuntimeException(\sprintf('Failed to write "%s" encoder file.', $path), previous: $e);
        }

        return $path;
    }

    private function getPath(Type $type): string
    {
        return \sprintf('%s%s%s.json.php', $this->encodersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) $type));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    private function createDataModel(Type $type, DataAccessorInterface $accessor, array $options = [], array $context = []): DataModelNodeInterface
    {
        $context['depth'] ??= 0;

        if ($context['depth'] > self::MAX_DEPTH) {
            return new ExceptionNode(MaxDepthException::class);
        }

        $context['original_type'] ??= $type;

        if ($type instanceof UnionType) {
            return new CompositeNode($accessor, array_map(fn (Type $t): DataModelNodeInterface => $this->createDataModel($t, $accessor, $options, $context), $type->getTypes()));
        }

        if ($type instanceof BuiltinType) {
            return new ScalarNode($accessor, $type);
        }

        if ($type instanceof BackedEnumType) {
            return new BackedEnumNode($accessor, $type);
        }

        if ($type instanceof ObjectType && !$type instanceof EnumType) {
            ++$context['depth'];

            $className = $type->getClassName();
            $propertiesMetadata = $this->propertyMetadataLoader->load($className, $options, ['original_type' => $type] + $context);

            try {
                $classReflection = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            $propertiesNodes = [];

            foreach ($propertiesMetadata as $encodedName => $propertyMetadata) {
                $propertyAccessor = new PropertyDataAccessor($accessor, $propertyMetadata->getName());

                foreach ($propertyMetadata->getNormalizers() as $normalizer) {
                    if (\is_string($normalizer)) {
                        $normalizerServiceAccessor = new FunctionDataAccessor('get', [new ScalarDataAccessor($normalizer)], new VariableDataAccessor('normalizers'));
                        $propertyAccessor = new FunctionDataAccessor('normalize', [$propertyAccessor, new VariableDataAccessor('options')], $normalizerServiceAccessor);

                        continue;
                    }

                    try {
                        $functionReflection = new \ReflectionFunction($normalizer);
                    } catch (\ReflectionException $e) {
                        throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }

                    $functionName = !$functionReflection->getClosureScopeClass()
                        ? $functionReflection->getName()
                        : \sprintf('%s::%s', $functionReflection->getClosureScopeClass()->getName(), $functionReflection->getName());
                    $arguments = $functionReflection->isUserDefined() ? [$propertyAccessor, new VariableDataAccessor('options')] : [$propertyAccessor];

                    $propertyAccessor = new FunctionDataAccessor($functionName, $arguments);
                }

                $propertiesNodes[$encodedName] = $this->createDataModel($propertyMetadata->getType(), $propertyAccessor, $options, $context);
            }

            return new ObjectNode($accessor, $type, $propertiesNodes);
        }

        if ($type instanceof CollectionType) {
            ++$context['depth'];

            return new CollectionNode(
                $accessor,
                $type,
                $this->createDataModel($type->getCollectionValueType(), new VariableDataAccessor('value'), $options, $context),
            );
        }

        throw new UnsupportedException(\sprintf('"%s" type is not supported.', (string) $type));
    }
}
