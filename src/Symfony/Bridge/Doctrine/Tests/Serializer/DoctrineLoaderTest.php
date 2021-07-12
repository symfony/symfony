<?php

declare(strict_types=1);

namespace Symfony\Bridge\Doctrine\Tests\Serializer;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Serializer\DoctrineLoader;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoctrineSerializerLoaderEntity;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class DoctrineLoaderTest extends TestCase
{
    public function datesProvider(): iterable
    {
        yield ['dateImmutable', 'Y-m-d'];
        yield ['dateMutable', 'Y-m-d'];
        yield ['timeImmutable', 'H:i:s'];
        yield ['timeMutable', 'H:i:s'];
    }

    /**
     * @dataProvider datesProvider
     */
    public function testDates(string $field, string $expectedFormat)
    {
        $entityManager = DoctrineTestHelper::createTestEntityManager();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects(self::any())
            ->method('getManagerForClass')
            ->with(DoctrineSerializerLoaderEntity::class)
            ->willReturn($entityManager);

        $loader = new DoctrineLoader($registry);

        $classMetadata = new ClassMetadata(DoctrineSerializerLoaderEntity::class);
        $loader->loadClassMetadata($classMetadata);

        self::assertSame(
            $expectedFormat,
            $classMetadata->getAttributesMetadata()[$field]->getNormalizationContexts(
            )['*'][DateTimeNormalizer::FORMAT_KEY]
        );
        self::assertSame(
            $expectedFormat,
            $classMetadata->getAttributesMetadata()[$field]->getDenormalizationContexts(
            )['*'][DateTimeNormalizer::FORMAT_KEY]
        );
    }
}
