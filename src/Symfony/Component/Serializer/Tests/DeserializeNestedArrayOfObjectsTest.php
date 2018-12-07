<?php
namespace Symfony\Component\Serializer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DeserializeNestedArrayOfObjectsTest extends TestCase
{
    public function provider()
    {
        return [
            //from property PhpDoc
            [Zoo::class],
            //from argument constructor PhpDoc
            [ZooImmutable::class],
        ];
    }
    /**
     * @dataProvider provider
     */
    public function testPropertyPhpDoc($class)
    {
        //GIVEN
        $json = <<<EOF
{
    "animals": [
        {"name": "Bug"}
    ]
}
EOF;
        $serializer = new Serializer(array(
            new ObjectNormalizer(null,null, null, new PhpDocExtractor()),
            new ArrayDenormalizer(),
        ), array('json' => new JsonEncoder()));
        //WHEN
        /** @var Zoo $zoo */
        $zoo = $serializer->deserialize($json, $class, 'json');
        //THEN
        self::assertCount(1, $zoo->getAnimals());
        self::assertInstanceOf(Animal::class, $zoo->getAnimals()[0]);
    }
}

class Zoo {
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
    public function setAnimals(array $animals): void
    {
        $this->animals = $animals;
    }
}

class ZooImmutable {
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

class Animal {
    /** @var string */
    private $name;
    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    /**
     * @param string $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
