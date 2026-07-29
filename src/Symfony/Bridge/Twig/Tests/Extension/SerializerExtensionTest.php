<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\SerializerExtension;
use Symfony\Bridge\Twig\Extension\SerializerRuntime;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\SerializerModelFixture;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
class SerializerExtensionTest extends TestCase
{
    #[DataProvider('serializerDataProvider')]
    public function testSerializeFilter(string $template, string $expectedResult)
    {
        $twig = $this->getTwig($template);

        self::assertSame($expectedResult, $twig->render('template', ['object' => new SerializerModelFixture()]));
    }

    public static function serializerDataProvider(): \Generator
    {
        yield ['{{ object|serialize }}', '{&quot;name&quot;:&quot;howdy&quot;,&quot;title&quot;:&quot;fixture&quot;}'];
        yield ['{{ object|serialize(\'yaml\') }}', '{ name: howdy, title: fixture }'];
        yield ['{{ object|serialize(\'yaml\', {groups: \'read\'}) }}', '{ name: howdy }'];
    }

    #[DataProvider('normalizerDataProvider')]
    public function testNormalizeFilter(string $template, string $expectedResult)
    {
        $twig = $this->getTwig($template);

        self::assertSame($expectedResult, $twig->render('template', ['object' => new SerializerModelFixture()]));
    }

    public static function normalizerDataProvider(): \Generator
    {
        yield ['{{ (object|normalize).name }} {{ (object|normalize).title }}', 'howdy fixture'];
        yield ['{{ object|normalize(\'json\', {groups: \'read\'})|keys|join(\',\') }}', 'name'];
    }

    public function testNormalizeWithoutFormatByDefault()
    {
        $normalizer = new class implements NormalizerInterface {
            public string|false|null $format = false;

            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                $this->format = $format;

                return [];
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => true];
            }
        };

        (new SerializerRuntime($normalizer))->normalize(new SerializerModelFixture());

        self::assertNull($normalizer->format);
    }

    public function testNormalizeRequiresANormalizer()
    {
        $runtime = new SerializerRuntime(new class implements SerializerInterface {
            public function serialize(mixed $data, string $format, array $context = []): string
            {
                return '';
            }

            public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
            {
                return null;
            }
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "normalize" filter requires a serializer implementing "%s"', NormalizerInterface::class));

        $runtime->normalize(new SerializerModelFixture());
    }

    public function testSerializeRequiresASerializer()
    {
        $runtime = new SerializerRuntime(new ObjectNormalizer());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "serialize" filter requires a serializer implementing "%s"', SerializerInterface::class));

        $runtime->serialize(new SerializerModelFixture());
    }

    private function getTwig(string $template): Environment
    {
        $meta = new ClassMetadataFactory(new AttributeLoader());
        $runtime = new SerializerRuntime(new Serializer([new ObjectNormalizer($meta)], [new JsonEncoder(), new YamlEncoder()]));

        $runtimeLoader = new ContainerRuntimeLoader(new ServiceLocator([
            SerializerRuntime::class => static fn () => $runtime,
        ]));

        $twig = new Environment(new ArrayLoader(['template' => $template]));
        $twig->addExtension(new SerializerExtension());
        $twig->addRuntimeLoader($runtimeLoader);

        return $twig;
    }
}
