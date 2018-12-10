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
    public function provider()
    {
        return array(
            //from property PhpDoc
            array(Zoo::class),
            //from argument constructor PhpDoc
            array(ZooImmutable::class),
        );
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
            new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
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

class Zoo
{
    /** @var Animal[] */
    private $animals = array();

    /**
     * @return Animal[]
     */
    public function getAnimals()
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
    private $animals = array();

    /**
     * @param Animal[] $animals
     */
    public function __construct(array $animals = array())
    {
        $this->animals = $animals;
    }

    /**
     * @return Animal[]
     */
    public function getAnimals()
    {
        return $this->animals;
    }
}

class Animal
{
    /** @var string */
    private $name;

    public function __construct()
    {
        echo '';
    }

    /**
     * @return string|null
     */
    public function getName()
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
