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

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\TranslatableBackedEnum;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\AppKernel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SerializerTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideDeserializeArrayOfObjectData
     */
    public function testDeserializeArrayOfObject(string $expectedClass, bool $usePromotedProperties, bool $withConstructorExtractor)
    {
        static::bootKernel(['test_case' => 'Serializer', 'root_config' => $withConstructorExtractor ? 'config.yml' : 'config_without_constructor_extractor.yml']);

        $result = static::getContainer()->get('serializer.alias')->deserialize('{"bars": [{"id": 1}, {"id": 2}]}', $expectedClass, 'json');

        $bar1 = new Bar();
        $bar1->id = 1;
        $bar2 = new Bar();
        $bar2->id = 2;

        if ($usePromotedProperties) {
            $expected = new ($expectedClass)([$bar1, $bar2]);
        } else {
            $expected = new $expectedClass();
            $expected->bars = [$bar1, $bar2];
        }

        $this->assertEquals($expected, $result);
    }

    public function testNormalizersAndEncodersUseDefaultContextConfigOption()
    {
        /** @var SerializerKernel $kernel */
        $kernel = static::bootKernel(['test_case' => 'Serializer', 'root_config' => 'default_context.yaml']);

        foreach ($kernel->normalizersAndEncoders as $normalizerOrEncoderId) {
            if (!static::getContainer()->has($normalizerOrEncoderId)) {
                continue;
            }

            $normalizerOrEncoder = static::getContainer()->get($normalizerOrEncoderId);

            $reflectionObject = new \ReflectionObject($normalizerOrEncoder);
            $property = $reflectionObject->getProperty('defaultContext');

            $defaultContext = $property->getValue($normalizerOrEncoder);

            self::assertArrayHasKey('fake_context_option', $defaultContext);
            self::assertEquals('foo', $defaultContext['fake_context_option']);
        }
    }

    protected static function getKernelClass(): string
    {
        return SerializerKernel::class;
    }

    public function provideDeserializeArrayOfObjectData(): array
    {
        return [
            ['expectedClass' => Foo::class, 'usePromotedProperties' => false, 'withConstructorExtractor' => false],
            ['expectedClass' => FooVar::class, 'usePromotedProperties' => true, 'withConstructorExtractor' => false],
            ['expectedClass' => FooParam::class, 'usePromotedProperties' => true, 'withConstructorExtractor' => false],
            ['expectedClass' => Foo::class, 'usePromotedProperties' => false, 'withConstructorExtractor' => true],
            ['expectedClass' => FooVar::class, 'usePromotedProperties' => true, 'withConstructorExtractor' => true],
            ['expectedClass' => FooParam::class, 'usePromotedProperties' => true, 'withConstructorExtractor' => true],
        ];
    }
}

class SerializerKernel extends AppKernel implements CompilerPassInterface
{
    public $normalizersAndEncoders = [
        'serializer.normalizer.property.alias', // Special case as this normalizer isn't tagged
    ];

    public function process(ContainerBuilder $container): void
    {
        $services = array_merge(
            $container->findTaggedServiceIds('serializer.normalizer'),
            $container->findTaggedServiceIds('serializer.encoder')
        );
        foreach ($services as $serviceId => $attributes) {
            $class = $container->getDefinition($serviceId)->getClass();
            if (null === $reflectionConstructor = (new \ReflectionClass($class))->getConstructor()) {
                continue;
            }
            foreach ($reflectionConstructor->getParameters() as $reflectionParam) {
                if ('array $defaultContext' === $reflectionParam->getType()->getName().' $'.$reflectionParam->getName()) {
                    $this->normalizersAndEncoders[] = $serviceId.'.alias';
                    break;
                }
            }
        }
    }

    public function testSerializeTranslatableBackedEnum()
    {
        static::bootKernel(['test_case' => 'Serializer']);

        $serializer = static::getContainer()->get('serializer.alias');

        $this->assertEquals('GET', $serializer->serialize(TranslatableBackedEnum::Get, 'yaml'));
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

class FooVar
{
    public function __construct(
        /**
         * @var Bar[]
         */
        public array $bars,
    ) {
    }
}

class FooParam
{
    /**
     * @param Bar[] $bars
     */
    public function __construct(
        public array $bars,
    ) {
    }
}
