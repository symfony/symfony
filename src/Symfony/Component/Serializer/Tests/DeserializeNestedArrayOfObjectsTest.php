<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DeserializeNestedArrayOfObjectsTest extends TestCase
{
    public static function provider()
    {
        return [
            // from property PhpDoc
            [Zoo::class],
            // from argument constructor PhpDoc
            [ZooImmutable::class],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function testPropertyPhpDoc($class)
    {
        $json = <<<EOF
{
    "animals": [
        {"name": "Bug"}
    ]
}
EOF;
        $serializer = new Serializer([
            new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
            new ArrayDenormalizer(),
        ], ['json' => new JsonEncoder()]);

        /** @var Zoo|ZooImmutable $zoo */
        $zoo = $serializer->deserialize($json, $class, 'json');

        self::assertCount(1, $zoo->getAnimals());
        self::assertInstanceOf(Animal::class, $zoo->getAnimals()[0]);
    }

    public function testPropertyPhpDocWithKeyTypes()
    {
        $json = <<<EOF
{
    "animalsInt": [
        {"name": "Bug"}
    ],
    "animalsString": {
        "animal1": {"name": "Bug"}
    },
    "animalsUnion": {
        "animal2": {"name": "Bug"},
        "2": {"name": "Dog"}
    },
    "animalsGenerics": {
        "animal3": {"name": "Bug"},
        "3": {"name": "Dog"}
    }
}
EOF;
        $serializer = new Serializer([
            new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
            new ArrayDenormalizer(),
        ], ['json' => new JsonEncoder()]);

        /** @var ZooWithKeyTypes $zoo */
        $zoo = $serializer->deserialize($json, ZooWithKeyTypes::class, 'json');

        self::assertCount(1, $zoo->animalsInt);
        self::assertArrayHasKey(0, $zoo->animalsInt);
        self::assertInstanceOf(Animal::class, $zoo->animalsInt[0]);

        self::assertCount(1, $zoo->animalsString);
        self::assertArrayHasKey('animal1', $zoo->animalsString);
        self::assertInstanceOf(Animal::class, $zoo->animalsString['animal1']);

        self::assertCount(2, $zoo->animalsUnion);
        self::assertArrayHasKey('animal2', $zoo->animalsUnion);
        self::assertInstanceOf(Animal::class, $zoo->animalsUnion['animal2']);
        self::assertArrayHasKey(2, $zoo->animalsUnion);
        self::assertInstanceOf(Animal::class, $zoo->animalsUnion[2]);

        self::assertCount(2, $zoo->animalsGenerics);
        self::assertArrayHasKey('animal3', $zoo->animalsGenerics);
        self::assertInstanceOf(Animal::class, $zoo->animalsGenerics['animal3']);
        self::assertArrayHasKey(3, $zoo->animalsGenerics);
        self::assertInstanceOf(Animal::class, $zoo->animalsGenerics[3]);
    }
}

class Zoo
{
    /** @var Animal[] */
    private $animals = [];

    /**
     * @return Animal[]
     */
    public function getAnimals(): array
    {
        return $this->animals;
    }

    /**
     * @param Animal[] $animals
     */
    public function setAnimals(array $animals)
    {
        $this->animals = $animals;
    }
}

class ZooImmutable
{
    /** @var Animal[] */
    private $animals = [];

    /**
     * @param Animal[] $animals
     */
    public function __construct(array $animals = [])
    {
        $this->animals = $animals;
    }

    /**
     * @return Animal[]
     */
    public function getAnimals(): array
    {
        return $this->animals;
    }
}

class ZooWithKeyTypes
{
    /** @var array<int, Animal> */
    public $animalsInt = [];
    /** @var array<string, Animal> */
    public $animalsString = [];
    /** @var array<int|string, Animal> */
    public $animalsUnion = [];
    /** @var \Traversable<Animal> */
    public $animalsGenerics = [];
}

class Animal
{
    /** @var string */
    private $name;

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}
