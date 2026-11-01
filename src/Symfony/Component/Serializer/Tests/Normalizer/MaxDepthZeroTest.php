<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\MaxDepthZeroDummy;

class MaxDepthZeroTest extends TestCase
{
    #[DataProvider('provideNormalizers')]
    public function testNormalizeWithHandler(string $normalizerClass, string $mapping)
    {
        $normalizer = $this->createNormalizer($normalizerClass, $mapping);
        $child = new MaxDepthZeroDummy('child');
        $root = new MaxDepthZeroDummy('root', $child);
        $calls = 0;
        $result = $normalizer->normalize($root, 'json', [
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            AbstractObjectNormalizer::MAX_DEPTH_HANDLER => function ($value, $object, $attribute, $format, $context) use ($child, $root, &$calls) {
                ++$calls;
                $this->assertSame($child, $value);
                $this->assertSame($root, $object);
                $this->assertSame('child', $attribute);
                $this->assertSame('json', $format);
                $this->assertTrue($context[AbstractObjectNormalizer::ENABLE_MAX_DEPTH]);

                return $value->name;
            },
        ]);

        $this->assertSame(['name' => 'root', 'child' => 'child'], $result);
        $this->assertSame(1, $calls);
    }

    #[DataProvider('provideNormalizers')]
    public function testNormalizeWithoutHandler(string $normalizerClass, string $mapping)
    {
        $root = new MaxDepthZeroDummy('root');
        $root->child = $root;

        $this->assertSame(['name' => 'root'], $this->createNormalizer($normalizerClass, $mapping)->normalize($root, null, [
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]));
    }

    #[DataProvider('provideNormalizers')]
    public function testNormalizeWithMaxDepthDisabled(string $normalizerClass, string $mapping)
    {
        $root = new MaxDepthZeroDummy('root', new MaxDepthZeroDummy('child'));

        $this->assertSame(['name' => 'root', 'child' => ['name' => 'child', 'child' => null]], $this->createNormalizer($normalizerClass, $mapping)->normalize($root));
    }

    public static function provideNormalizers(): iterable
    {
        foreach ([ObjectNormalizer::class, PropertyNormalizer::class, GetSetMethodNormalizer::class] as $normalizer) {
            foreach (['attributes', 'yaml', 'xml'] as $mapping) {
                yield $normalizer.' '.$mapping => [$normalizer, $mapping];
            }
        }
    }

    private function createNormalizer(string $normalizerClass, string $mapping): AbstractObjectNormalizer
    {
        $loader = match ($mapping) {
            'attributes' => new AttributeLoader(),
            'yaml' => new YamlFileLoader(__DIR__.'/../Fixtures/serialization.yml'),
            'xml' => new XmlFileLoader(__DIR__.'/../Fixtures/serialization.xml'),
        };
        $normalizer = new $normalizerClass(new ClassMetadataFactory($loader));
        new Serializer([$normalizer]);

        return $normalizer;
    }
}
