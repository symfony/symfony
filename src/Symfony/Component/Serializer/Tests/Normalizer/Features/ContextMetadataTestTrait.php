<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Test context handling from Serializer metadata.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
trait ContextMetadataTestTrait
{
    /**
     * @dataProvider contextMetadataDummyProvider
     */
    public function testContextMetadataNormalize(string $contextMetadataDummyClass)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, new PhpDocExtractor());
        new Serializer([new DateTimeNormalizer(), $normalizer]);

        $dummy = new $contextMetadataDummyClass();
        $dummy->date = new \DateTime('2011-07-28T08:44:00.123+00:00');

        self::assertEquals(['date' => '2011-07-28T08:44:00+00:00'], $normalizer->normalize($dummy));

        self::assertEquals(['date' => '2011-07-28T08:44:00.123+00:00'], $normalizer->normalize($dummy, null, [
            ObjectNormalizer::GROUPS => 'extended',
        ]), 'a specific normalization context is used for this group');

        self::assertEquals(['date' => '2011-07-28T08:44:00+00:00'], $normalizer->normalize($dummy, null, [
            ObjectNormalizer::GROUPS => 'simple',
        ]), 'base denormalization context is unchanged for this group');
    }

    /**
     * @dataProvider contextMetadataDummyProvider
     */
    public function testContextMetadataContextDenormalize(string $contextMetadataDummyClass)
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, new PhpDocExtractor());
        new Serializer([new DateTimeNormalizer(), $normalizer]);

        /** @var ContextMetadataDummy|ContextChildMetadataDummy $dummy */
        $dummy = $normalizer->denormalize(['date' => '2011-07-28T08:44:00+00:00'], $contextMetadataDummyClass);
        self::assertEquals(new \DateTime('2011-07-28T08:44:00+00:00'), $dummy->date);

        /** @var ContextMetadataDummy|ContextChildMetadataDummy $dummy */
        $dummy = $normalizer->denormalize(['date' => '2011-07-28T08:44:00+00:00'], ContextMetadataDummy::class, null, [
            ObjectNormalizer::GROUPS => 'extended',
        ]);
        self::assertEquals(new \DateTime('2011-07-28T08:44:00+00:00'), $dummy->date, 'base denormalization context is unchanged for this group');

        /** @var ContextMetadataDummy|ContextChildMetadataDummy $dummy */
        $dummy = $normalizer->denormalize(['date' => '28/07/2011'], $contextMetadataDummyClass, null, [
            ObjectNormalizer::GROUPS => 'simple',
        ]);
        self::assertEquals('2011-07-28', $dummy->date->format('Y-m-d'), 'a specific denormalization context is used for this group');
    }

    public function contextMetadataDummyProvider()
    {
        return [
            [ContextMetadataDummy::class],
            [ContextChildMetadataDummy::class],
        ];
    }

    public function testContextDenormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter(), null, new PhpDocExtractor());
        new Serializer([new DateTimeNormalizer(), $normalizer]);

        /** @var ContextMetadataNamingDummy $dummy */
        $dummy = $normalizer->denormalize(['created_at' => '28/07/2011'], ContextMetadataNamingDummy::class);
        self::assertEquals('2011-07-28', $dummy->createdAt->format('Y-m-d'));
    }
}

class ContextMetadataDummy
{
    /**
     * @var \DateTime
     *
     * @Groups({ "extended", "simple" })
     *
     * @Context({ DateTimeNormalizer::FORMAT_KEY = \DateTime::RFC3339 })
     * @Context(
     *     normalizationContext = { DateTimeNormalizer::FORMAT_KEY = \DateTime::RFC3339_EXTENDED },
     *     groups = {"extended"}
     * )
     * @Context(
     *     denormalizationContext = { DateTimeNormalizer::FORMAT_KEY = "d/m/Y" },
     *     groups = {"simple"}
     * )
     */
    public $date;
}

class ContextChildMetadataDummy
{
    /**
     * @var \DateTime
     *
     * @Groups({ "extended", "simple" })
     *
     * @DummyContextChild({ DateTimeNormalizer::FORMAT_KEY = \DateTime::RFC3339 })
     * @DummyContextChild(
     *     normalizationContext = { DateTimeNormalizer::FORMAT_KEY = \DateTime::RFC3339_EXTENDED },
     *     groups = {"extended"}
     * )
     * @DummyContextChild(
     *     denormalizationContext = { DateTimeNormalizer::FORMAT_KEY = "d/m/Y" },
     *     groups = {"simple"}
     * )
     */
    public $date;
}

class ContextMetadataNamingDummy
{
    /**
     * @var \DateTime
     *
     * @Context({ DateTimeNormalizer::FORMAT_KEY = "d/m/Y" })
     */
    public $createdAt;
}
