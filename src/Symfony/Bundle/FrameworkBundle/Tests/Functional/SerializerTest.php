<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SerializerTest extends AbstractWebTestCase
{
    public function testDeserializeArrayOfObject()
    {
        static::bootKernel(['test_case' => 'Serializer']);

        $result = static::getContainer()->get('serializer.alias')->deserialize('{"bars": [{"id": 1}, {"id": 2}]}', Foo::class, 'json');

        $bar1 = new Bar();
        $bar1->id = 1;
        $bar2 = new Bar();
        $bar2->id = 2;

        $expected = new Foo();
        $expected->bars = [$bar1, $bar2];

        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provideNormalizersAndEncodersWithDefaultContextOption
     */
    public function testNormalizersAndEncodersUseDefaultContextConfigOption(string $normalizerId)
    {
        static::bootKernel(['test_case' => 'Serializer']);

        $normalizer = static::getContainer()->get($normalizerId);

        $reflectionObject = new \ReflectionObject($normalizer);
        $property = $reflectionObject->getProperty('defaultContext');
        $property->setAccessible(true);

        $defaultContext = $property->getValue($normalizer);

        self::assertArrayHasKey('fake_context_option', $defaultContext);
        self::assertEquals('foo', $defaultContext['fake_context_option']);
    }

    public static function provideNormalizersAndEncodersWithDefaultContextOption(): array
    {
        return [
            ['serializer.normalizer.constraint_violation_list.alias'],
            ['serializer.normalizer.dateinterval.alias'],
            ['serializer.normalizer.datetime.alias'],
            ['serializer.normalizer.json_serializable.alias'],
            ['serializer.normalizer.problem.alias'],
            ['serializer.normalizer.uid.alias'],
            ['serializer.normalizer.object.alias'],
            ['serializer.encoder.xml.alias'],
            ['serializer.encoder.yaml.alias'],
            ['serializer.encoder.csv.alias'],
        ];
    }
}

class Foo
{
    /**
     * @var Bar[]
     */
    public $bars;
}

class Bar
{
    /**
     * @var int
     */
    public $id;
}
