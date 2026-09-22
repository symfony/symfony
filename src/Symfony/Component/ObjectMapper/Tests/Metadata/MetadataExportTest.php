<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Metadata;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Cost;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\CostRequestWithSourceAndAutoMappedView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MagicGet\MagicGetUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MagicGet\MagicGetUserView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntityDto;
use Symfony\Component\VarExporter\VarExporter;

class MetadataExportTest extends TestCase
{
    #[DataProvider('provideMappedPairs')]
    public function testMetadataIsStaticExceptForTheMappingsItCarries(object $source, object $target)
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $classMetadataFactory = new ReflectionClassMetadataFactory($metadataFactory);
        $propertyMetadataFactory = new ReflectionPropertyMetadataFactory($metadataFactory, $classMetadataFactory);
        $propertyNameCollectionFactory = new ReflectionPropertyNameCollectionFactory();

        $this->assertOnlyMappingsAreObjects($classMetadataFactory->create($source, $target), 'class metadata');
        $this->assertOnlyMappingsAreObjects($propertyNameCollectionFactory->create($source), 'source property names');
        $this->assertOnlyMappingsAreObjects($propertyNameCollectionFactory->create($target), 'target property names');

        $properties = [...$propertyNameCollectionFactory->create($source), ...$propertyNameCollectionFactory->create($target)];
        $this->assertNotEmpty($properties);

        foreach (array_unique($properties) as $property) {
            $this->assertOnlyMappingsAreObjects($propertyMetadataFactory->create($source, $target, $property), $property);
        }
    }

    #[DataProvider('provideMappedPairs')]
    public function testMetadataSurvivesAVarExporterRoundTrip(object $source, object $target)
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $classMetadataFactory = new ReflectionClassMetadataFactory($metadataFactory);
        $propertyMetadataFactory = new ReflectionPropertyMetadataFactory($metadataFactory, $classMetadataFactory);
        $propertyNameCollectionFactory = new ReflectionPropertyNameCollectionFactory();

        $classMetadata = $classMetadataFactory->create($source, $target);
        $this->assertEquals($classMetadata, $this->roundTrip($classMetadata));

        foreach (array_unique([...$propertyNameCollectionFactory->create($source), ...$propertyNameCollectionFactory->create($target)]) as $property) {
            $propertyMetadata = $propertyMetadataFactory->create($source, $target, $property);
            $this->assertEquals($propertyMetadata, $this->roundTrip($propertyMetadata), $property);
        }
    }

    public static function provideMappedPairs(): iterable
    {
        yield 'source carries the metadata and the target has a constructor' => [new ChildEntity(1, 'foo'), (new \ReflectionClass(ChildEntityDto::class))->newInstanceWithoutConstructor()];
        yield 'metadata is read from the target' => [new Cost(1, 2), new CostRequestWithSourceAndAutoMappedView()];
        yield 'private property read through __get' => [new MagicGetUser(), new MagicGetUserView()];
    }

    private function assertOnlyMappingsAreObjects(mixed $value, string $message): void
    {
        $objects = [];
        $this->collectObjectTypes($value, $objects);

        $this->assertSame([], array_diff(array_unique($objects), [Mapping::class]), $message.': only Mapping instances may be objects');

        VarExporter::export($value, $isStaticValue);
        $this->assertSame([] === $objects, $isStaticValue, $message.': static iff it carries no Mapping');
    }

    /**
     * @param list<class-string> $objects
     */
    private function collectObjectTypes(mixed $value, array &$objects): void
    {
        if (\is_object($value)) {
            $objects[] = $value::class;

            return;
        }

        if (\is_array($value)) {
            foreach ($value as $item) {
                $this->collectObjectTypes($item, $objects);
            }
        }
    }

    private function roundTrip(mixed $value): mixed
    {
        return eval('return '.VarExporter::export($value).';');
    }
}
