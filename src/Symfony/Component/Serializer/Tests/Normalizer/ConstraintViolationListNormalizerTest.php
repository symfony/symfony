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
                        'type' => 'urn:uuid:f',
                        'parameters' => [
                            'value' => 'foo',
                        ],
                    ],
                    [
                        'propertyPath' => '4',
                        'title' => '1',
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
            new ConstraintViolation('too short', 'a', [], 'c', 'shortDescription', ''),
            new ConstraintViolation('too long', 'b', [], '3', 'product.shortDescription', 'Lorem ipsum dolor sit amet'),
            new ConstraintViolation('error', 'b', [], '3', '', ''),
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
                    'parameters' => [],
                ],
                [
                    'propertyPath' => 'product.short_description',
                    'title' => 'too long',
                    'parameters' => [],
                ],
                [
                    'propertyPath' => '',
                    'title' => 'error',
                    'parameters' => [],
                ],
            ],
        ];

        $this->assertEquals($expected, $normalizer->normalize($list));
    }
}
