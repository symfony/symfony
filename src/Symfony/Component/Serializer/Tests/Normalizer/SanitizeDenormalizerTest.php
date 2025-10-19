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
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\SanitizeDenormalizer;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithArrayOfStrings;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithCustomSanitizer;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithInvalidArray;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithObject;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithInvalidType;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithArrayOfObjects;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SanitizeDummyWithUnknownSanitizer;

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
        $customSanitizer = [
            'custom' => new HtmlSanitizer(
                (new HtmlSanitizerConfig())
                    ->allowElement('img', ['src', 'alt'])
                    ->allowElement('h1')
                    ->allowElement('div')
            ),
        ];
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor(typeExtractors: [$phpDocExtractor, $reflectionExtractor]);
        $objectNormalizer = new ObjectNormalizer(propertyTypeExtractor:  $propertyInfo);
        $sanitizeDenormalizer = new SanitizeDenormalizer($sanitizer, $customSanitizer);
        $sanitizeDenormalizer->setDenormalizer($objectNormalizer);
        $arrayDenormalizer = new ArrayDenormalizer();
        $arrayDenormalizer->setDenormalizer($sanitizeDenormalizer);

        $this->serializer = new Serializer([
            $arrayDenormalizer,
            $sanitizeDenormalizer,
            $objectNormalizer,
        ]);

    }

   public function testDenormalizeObject(): void
    {
        // Arrange
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'firstName' => '<b>John</b>',
            'lastName' => '<i>Doe</i>',
            'bio' => '<script>alert("xss")</script>'
        ];

        // Act
        $result = $this->serializer->denormalize($data, SanitizeDummy::class);

        // Assert
        $this->assertInstanceOf(SanitizeDummy::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertSame('<b>John</b>', $result->firstName);
        $this->assertSame('<i>Doe</i>', $result->lastName);
        $this->assertSame('', $result->bio);
    }

    public function testDenormalizeObjectWithCustomSanitizer(): void
    {
        // Arrange
        $data = [
            'foo' => '<img src="https://symfony.com" onclick="alert(\'xss\')" alt="symfony" title="symfony"/>',
        ];

        // Act
        $result = $this->serializer->denormalize($data, SanitizeDummyWithCustomSanitizer::class);

        // Assert
        $this->assertInstanceOf(SanitizeDummyWithCustomSanitizer::class, $result);
        $this->assertSame('<img src="https://symfony.com" alt="symfony" />', $result->foo);
    }

    public function testDenormalizeNestedObject(): void
    {
        // Arrange
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'object' => [
                'id' => '223e4567-e89b-12d3-a456-426614174000',
                'firstName' => '<b>John</b>',
                'lastName' => '<i>Doe</i>',
                'bio' => '<script>alert("xss")</script>'
            ]
        ];

        // Act
        $result = $this->serializer->denormalize($data, SanitizeDummyWithObject::class);

        // Assert
        $this->assertInstanceOf(SanitizeDummyWithObject::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertInstanceOf(SanitizeDummy::class, $result->object);
        $this->assertSame('223e4567-e89b-12d3-a456-426614174000', $result->object->id);
        $this->assertSame('<b>John</b>', $result->object->firstName);
        $this->assertSame('<i>Doe</i>', $result->object->lastName);
        $this->assertSame('', $result->object->bio);
    }

    public function testDenormalizeArray(): void
    {
        // Arrange
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'objects' => [
                [
                    'id' => '223e4567-e89b-12d3-a456-426614174000',
                    'firstName' => '<b>John</b>',
                    'lastName' => '<i>Doe</i>',
                    'bio' => '<script>alert("xss")</script>'
                ],
                [
                    'id' => '323e4567-e89b-12d3-a456-426614174000',
                    'firstName' => '<b>Jane</b>',
                    'lastName' => '<i>Smith</i>',
                    'bio' => '<img src="https://symfony.com" onclick="alert(\'xss\')" alt="symfony" title="symfony"/>'
                ]
            ]
        ];

        // Act
        $result = $this->serializer->denormalize($data, SanitizeDummyWithArrayOfObjects::class);

        // Assert
        $this->assertInstanceOf(SanitizeDummyWithArrayOfObjects::class, $result);
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

    public function testDenormalizeArrayOfStrings(): void
    {
        // Arrange
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'strings' => [
                '<b>String 1</b>',
                '<i>String 2</i>',
                '<script>alert("xss")</script>'
            ]
        ];

        // Act
        $result = $this->serializer->denormalize($data, SanitizeDummyWithArrayOfStrings::class);

        // Assert
        $this->assertInstanceOf(SanitizeDummyWithArrayOfStrings::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertCount(3, $result->strings);
        $this->assertSame('<b>String 1</b>', $result->strings[0]);
        $this->assertSame('<i>String 2</i>', $result->strings[1]);
        $this->assertSame('', $result->strings[2]);
    }

    public function testDenormalizeWithAttributeOnNonStringPropertyThrowsLogicException(): void
    {
        // Arrange
        $data = [
            'foo' => 42,
        ];

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The #[Sanitize] attribute can only be applied to string or array of string properties. Property $%s in class %s is not supported.',
            'foo',
            SanitizeDummyWithInvalidType::class)
        );

        // Act
        $this->serializer->denormalize($data, SanitizeDummyWithInvalidType::class);
    }

    public function testDenormalizeWithAttributeOnInvalidArrayPropertyThrowsLogicException(): void
    {
        // Arrange
        $data = [
            'foo' => [42, 43],
        ];

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The #[Sanitize] attribute can only be applied to array of string properties. Property $%s in class %s contains a non-string value.',
            'foo',
            SanitizeDummyWithInvalidArray::class)
        );

        // Act
        $this->serializer->denormalize($data, SanitizeDummyWithInvalidArray::class);
    }

    public function testDenormalizeWithUnknownSanitizerThrowsInvalidArgumentException(): void
    {
        // Arrange
        $data = [
            'foo' => '<b>Test</b>',
        ];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The HTML sanitizer "unknown" does not exist.');

        // Act
        $this->serializer->denormalize($data, SanitizeDummyWithUnknownSanitizer::class, null, [
            'sanitizers' => [
                'default' => new HtmlSanitizer(
                    (new HtmlSanitizerConfig())->allowSafeElements()
                ),
            ],
        ]);
    }
}
