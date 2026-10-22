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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Dummy\DummyClassOne;
use Symfony\Component\Serializer\Tests\Dummy\DummyClassTwo;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ConstraintViolationListNormalizerTest extends TestCase
{
    private ConstraintViolationListNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ConstraintViolationListNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ConstraintViolationList()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $list = new ConstraintViolationList([
            new ConstraintViolation('a', 'b', ['value' => 'foo'], 'c', 'd', 'e', null, 'f'),
            new ConstraintViolation('1', '2', [], '3', '4', '5', null, '6'),
        ]);

        $expected = [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'detail' => 'd: a
4: 1',
            'violations' => [
                [
                    'propertyPath' => 'd',
                    'title' => 'a',
                    'template' => 'b',
                    'type' => 'urn:uuid:f',
                    'parameters' => [
                        'value' => 'foo',
                    ],
                ],
                [
                    'propertyPath' => '4',
                    'title' => '1',
                    'template' => '2',
                    'type' => 'urn:uuid:6',
                    'parameters' => [],
                ],
            ],
        ];

        $this->assertEquals($expected, $this->normalizer->normalize($list));
    }

    #[DataProvider('provideIgnoredAttributes')]
    public function testNormalizeWithIgnoredAttributes(array $defaultContext, array $context, array $expected)
    {
        $normalizer = new ConstraintViolationListNormalizer($defaultContext);
        $list = new ConstraintViolationList([
            new ConstraintViolation('Invalid', 'Template', ['type' => 'parameter'], null, 'name', null, null, 'code'),
        ]);

        $this->assertSame($expected, $normalizer->normalize($list, null, $context));
    }

    public static function provideIgnoredAttributes(): iterable
    {
        $violation = [
            'propertyPath' => 'name',
            'title' => 'Invalid',
            'template' => 'Template',
            'parameters' => ['type' => 'parameter'],
            'type' => 'urn:uuid:code',
        ];
        $complete = [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'detail' => 'name: Invalid',
            'violations' => [$violation],
        ];
        $withoutType = [
            'title' => 'Validation Failed',
            'detail' => 'name: Invalid',
            'violations' => [[
                'propertyPath' => 'name',
                'title' => 'Invalid',
                'template' => 'Template',
                'parameters' => ['type' => 'parameter'],
            ]],
        ];

        yield 'context' => [[], [AbstractNormalizer::IGNORED_ATTRIBUTES => ['type']], $withoutType];
        yield 'default context' => [[AbstractNormalizer::IGNORED_ATTRIBUTES => ['type']], [], $withoutType];
        yield 'override defaults' => [[AbstractNormalizer::IGNORED_ATTRIBUTES => ['type']], [AbstractNormalizer::IGNORED_ATTRIBUTES => []], $complete];
        yield 'null falls back to defaults' => [[AbstractNormalizer::IGNORED_ATTRIBUTES => ['type']], [AbstractNormalizer::IGNORED_ATTRIBUTES => null], $withoutType];
        yield 'unknown attribute' => [[], [AbstractNormalizer::IGNORED_ATTRIBUTES => ['unknown']], $complete];
        yield 'multiple attributes' => [[], [AbstractNormalizer::IGNORED_ATTRIBUTES => ['title', 'template', 'parameters']], [
            'type' => 'https://symfony.com/errors/validation',
            'detail' => 'name: Invalid',
            'violations' => [[
                'propertyPath' => 'name',
                'type' => 'urn:uuid:code',
            ]],
        ]];
        yield 'ignore violations' => [[], [AbstractNormalizer::IGNORED_ATTRIBUTES => ['violations']], [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'detail' => 'name: Invalid',
        ]];
        yield 'ignore all attributes' => [[], [AbstractNormalizer::IGNORED_ATTRIBUTES => ['type', 'title', 'detail', 'violations']], []];
    }

    public function testSerializeWithIgnoredAttributes()
    {
        $constraint = new NotNull();
        $constraint->payload = ['type' => 'payload type'];
        $list = new ConstraintViolationList([
            new ConstraintViolation('Invalid', 'Template', [], null, 'name', null, null, 'code', $constraint),
            new ConstraintViolation('Missing', 'Template', [], null, 'email', null),
        ]);
        $serializer = new Serializer([$this->normalizer], [new JsonEncoder()]);

        $this->assertSame([
            'title' => 'Validation Failed',
            'detail' => "name: Invalid\nemail: Missing",
            'violations' => [
                ['propertyPath' => 'name', 'title' => 'Invalid', 'parameters' => [], 'payload' => ['type' => 'payload type']],
                ['propertyPath' => 'email', 'title' => 'Missing', 'parameters' => []],
            ],
        ], json_decode($serializer->serialize($list, 'json', [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['type', 'template'],
            ConstraintViolationListNormalizer::PAYLOAD_FIELDS => true,
        ]), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testNormalizeWithNameConverter()
    {
        $normalizer = new ConstraintViolationListNormalizer([], new CamelCaseToSnakeCaseNameConverter());

        $list = new ConstraintViolationList([
            new ConstraintViolation('too short', 'a', [], '3', 'shortDescription', ''),
            new ConstraintViolation('too long', 'b', [], '3', 'product.shortDescription', 'Lorem ipsum dolor sit amet'),
            new ConstraintViolation('error', 'c', [], '3', '', ''),
        ]);

        $expected = [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'detail' => 'short_description: too short
product.short_description: too long
error',
            'violations' => [
                [
                    'propertyPath' => 'short_description',
                    'title' => 'too short',
                    'template' => 'a',
                    'parameters' => [],
                ],
                [
                    'propertyPath' => 'product.short_description',
                    'title' => 'too long',
                    'template' => 'b',
                    'parameters' => [],
                ],
                [
                    'propertyPath' => '',
                    'title' => 'error',
                    'template' => 'c',
                    'parameters' => [],
                ],
            ],
        ];

        $this->assertEquals($expected, $normalizer->normalize($list));
    }

    public function testNormalizeWithMetadataAwareNameConverter()
    {
        $attributeLoader = new AttributeLoader();
        $attributeLoader->loadClassMetadata(new ClassMetadata(DummyClassOne::class));

        $nameConverter = new MetadataAwareNameConverter(new ClassMetadataFactory($attributeLoader));
        $normalizer = new ConstraintViolationListNormalizer([], $nameConverter);

        $list = new ConstraintViolationList([
            new ConstraintViolation('too long', 'a', [], new DummyClassOne(), 'code', ''),
        ]);

        $expected = [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'detail' => 'identifier: too long',
            'violations' => [
                [
                    'propertyPath' => 'identifier',
                    'title' => 'too long',
                    'template' => 'a',
                    'parameters' => [],
                ],
            ],
        ];

        $this->assertEquals($expected, $normalizer->normalize($list));
    }

    public function testNormalizeWithMetadataAwareNameConverterAndNestedPath()
    {
        $attributeLoader = new AttributeLoader();
        $attributeLoader->loadClassMetadata(new ClassMetadata(DummyClassTwo::class));
        $attributeLoader->loadClassMetadata(new ClassMetadata(DummyClassOne::class));

        $nameConverter = new MetadataAwareNameConverter(new ClassMetadataFactory($attributeLoader));
        $normalizer = new ConstraintViolationListNormalizer([], $nameConverter);

        $root = new DummyClassTwo();
        $list = new ConstraintViolationList([
            new ConstraintViolation('too long', 'a', [], $root, 'child.code', ''),
        ]);

        $expected = [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'detail' => 'nested.identifier: too long',
            'violations' => [
                [
                    'propertyPath' => 'nested.identifier',
                    'title' => 'too long',
                    'template' => 'a',
                    'parameters' => [],
                ],
            ],
        ];

        $this->assertEquals($expected, $normalizer->normalize($list));
    }

    #[DataProvider('payloadFieldsProvider')]
    public function testNormalizePayloadFields($fields, ?array $expected = null)
    {
        $constraint = new NotNull();
        $constraint->payload = ['severity' => 'warning', 'anotherField2' => 'aValue'];
        $list = new ConstraintViolationList([
            new ConstraintViolation('a', 'b', [], 'c', 'd', 'e', null, null, $constraint),
        ]);

        $violation = $this->normalizer->normalize($list, null, [ConstraintViolationListNormalizer::PAYLOAD_FIELDS => $fields])['violations'][0];
        if ([] === $fields) {
            $this->assertArrayNotHasKey('payload', $violation);

            return;
        }
        $this->assertSame($expected, $violation['payload']);
    }

    public static function payloadFieldsProvider(): iterable
    {
        yield [['severity', 'anotherField1'], ['severity' => 'warning']];
        yield [null, ['severity' => 'warning', 'anotherField2' => 'aValue']];
        yield [true, ['severity' => 'warning', 'anotherField2' => 'aValue']];
        yield [[]];
    }
}
