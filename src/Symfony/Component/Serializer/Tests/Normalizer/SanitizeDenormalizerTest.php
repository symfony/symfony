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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\SanitizeDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithArrayOfObject;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithArrayOfString;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithCustomSanitizer;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithInvalidArray;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithInvalidType;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithObject;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithUnknownSanitizer;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
class SanitizeDenormalizerTest extends TestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig())->allowSafeElements()
        );
        $customSanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowElement('img', ['src', 'alt'])
                ->allowElement('h1')
                ->allowElement('div')
        );
        $container = new class(['default' => fn () => $sanitizer, 'custom' => fn () => $customSanitizer]) implements ServiceProviderInterface {
            use ServiceLocatorTrait;
        };
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor([], [$phpDocExtractor, $reflectionExtractor]);
        $objectNormalizer = new ObjectNormalizer(null, null, null, $propertyInfo);
        $sanitizeDenormalizer = new SanitizeDenormalizer($container);
        $arrayDenormalizer = new ArrayDenormalizer();

        $this->serializer = new Serializer([
            $arrayDenormalizer,
            $sanitizeDenormalizer,
            $objectNormalizer,
        ]);
    }

    public function testDenormalizeObject()
    {
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'firstName' => '<b>John</b>',
            'lastName' => '<i>Doe</i>',
            'bio' => '<script>alert("xss")</script>',
        ];

        $result = $this->serializer->denormalize($data, SanitizeDummy::class, 'json');

        $this->assertInstanceOf(SanitizeDummy::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertSame('<b>John</b>', $result->firstName);
        $this->assertSame('<i>Doe</i>', $result->lastName);
        $this->assertSame('', $result->bio);
    }

    public function testDenormalizeObjectWithCustomSanitizer()
    {
        $data = [
            'foo' => '<img src="https://symfony.com" onclick="alert(\'xss\')" alt="symfony" title="symfony"/>',
        ];

        $result = $this->serializer->denormalize($data, SanitizeDummyWithCustomSanitizer::class);

        $this->assertInstanceOf(SanitizeDummyWithCustomSanitizer::class, $result);
        $this->assertSame('<img src="https://symfony.com" alt="symfony" />', $result->foo);
    }

    public function testDenormalizeNestedObject()
    {
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'object' => [
                'id' => '223e4567-e89b-12d3-a456-426614174000',
                'firstName' => '<b>John</b>',
                'lastName' => '<i>Doe</i>',
                'bio' => '<script>alert("xss")</script>',
            ],
        ];

        $result = $this->serializer->denormalize($data, SanitizeDummyWithObject::class);

        $this->assertInstanceOf(SanitizeDummyWithObject::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertInstanceOf(SanitizeDummy::class, $result->object);
        $this->assertSame('223e4567-e89b-12d3-a456-426614174000', $result->object->id);
        $this->assertSame('<b>John</b>', $result->object->firstName);
        $this->assertSame('<i>Doe</i>', $result->object->lastName);
        $this->assertSame('', $result->object->bio);
    }

    public function testDenormalizeArrayOfObject()
    {
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'objects' => [
                [
                    'id' => '223e4567-e89b-12d3-a456-426614174000',
                    'firstName' => '<b>John</b>',
                    'lastName' => '<i>Doe</i>',
                    'bio' => '<script>alert("xss")</script>',
                ],
                [
                    'id' => '323e4567-e89b-12d3-a456-426614174000',
                    'firstName' => '<b>Jane</b>',
                    'lastName' => '<i>Smith</i>',
                    'bio' => '<img src="https://symfony.com" onclick="alert(\'xss\')" alt="symfony" title="symfony"/>',
                ],
            ],
        ];

        $result = $this->serializer->denormalize($data, SanitizeDummyWithArrayOfObject::class);

        $this->assertInstanceOf(SanitizeDummyWithArrayOfObject::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertCount(2, $result->objects);
        $this->assertInstanceOf(SanitizeDummy::class, $result->objects[0]);
        $this->assertSame('223e4567-e89b-12d3-a456-426614174000', $result->objects[0]->id);
        $this->assertSame('<b>John</b>', $result->objects[0]->firstName);
        $this->assertSame('<i>Doe</i>', $result->objects[0]->lastName);
        $this->assertSame('', $result->objects[0]->bio);
        $this->assertInstanceOf(SanitizeDummy::class, $result->objects[1]);
        $this->assertSame('323e4567-e89b-12d3-a456-426614174000', $result->objects[1]->id);
        $this->assertSame('<b>Jane</b>', $result->objects[1]->firstName);
        $this->assertSame('<i>Smith</i>', $result->objects[1]->lastName);
        $this->assertSame('<img src="https://symfony.com" alt="symfony" title="symfony" />', $result->objects[1]->bio);
    }

    public function testDenormalizeArrayOfStrings()
    {
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'strings' => [
                '<b>String 1</b>',
                '<i>String 2</i>',
                '<script>alert("xss")</script>',
            ],
        ];

        $result = $this->serializer->denormalize($data, SanitizeDummyWithArrayOfString::class);

        $this->assertInstanceOf(SanitizeDummyWithArrayOfString::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertCount(3, $result->strings);
        $this->assertSame('<b>String 1</b>', $result->strings[0]);
        $this->assertSame('<i>String 2</i>', $result->strings[1]);
        $this->assertSame('', $result->strings[2]);
    }

    public function testDenormalizeWithAttributeOnNonStringPropertyThrowsLogicException()
    {
        $data = [
            'foo' => 42,
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot sanitize property "foo". Only string or array of strings are supported.');

        $this->serializer->denormalize($data, SanitizeDummyWithInvalidType::class);
    }

    public function testDenormalizeWithAttributeOnInvalidArrayPropertyThrowsLogicException()
    {
        $data = [
            'foo' => [42, 43],
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot sanitize property "foo". Only string items are supported.');

        $this->serializer->denormalize($data, SanitizeDummyWithInvalidArray::class);
    }

    public function testDenormalizeWithUnknownSanitizerThrowsInvalidArgumentException()
    {
        $data = [
            'foo' => '<b>Test</b>',
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Sanitizer "unknown" is not defined in the container.');

        $this->serializer->denormalize($data, SanitizeDummyWithUnknownSanitizer::class);
    }
}
