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
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ConstraintViolationListNormalizerTest extends TestCase
{
    private $normalizer;

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

    /**
     * @dataProvider payloadFieldsProvider
     */
    public function testNormalizePayloadFields($fields, array $expected = null)
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
