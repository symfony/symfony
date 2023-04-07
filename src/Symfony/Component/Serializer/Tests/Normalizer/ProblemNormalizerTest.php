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
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ProblemNormalizerTest extends TestCase
{
    /**
     * @var ProblemNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProblemNormalizer(false);
    }

    public function testSupportNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(FlattenException::createFromThrowable(new \Exception())));
        $this->assertFalse($this->normalizer->supportsNormalization(new \Exception()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $expected = [
            'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title' => 'An error occurred',
            'status' => 500,
            'detail' => 'Internal Server Error',
        ];

        $this->assertSame($expected, $this->normalizer->normalize(FlattenException::createFromThrowable(new \RuntimeException('Error'))));
    }

    public function testNormalizePartialDenormalizationException()
    {
        $this->normalizer->setSerializer(new Serializer());

        $expected = [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'status' => 422,
            'detail' => 'foo: This value should be of type int.',
            'violations' => [
                [
                    'propertyPath' => 'foo',
                    'title' => 'This value should be of type int.',
                    'template' => 'This value should be of type {{ type }}.',
                    'parameters' => [
                        '{{ type }}' => 'int',
                    ],
                    'hint' => 'Invalid value',
                ],
            ],
        ];

        $exception = NotNormalizableValueException::createForUnexpectedDataType('Invalid value', null, ['int'], 'foo', true);
        $exception = new PartialDenormalizationException('Validation Failed', [$exception]);
        $exception = new HttpException(422, 'Validation Failed', $exception);
        $this->assertSame($expected, $this->normalizer->normalize(FlattenException::createFromThrowable($exception), null, ['exception' => $exception]));
    }

    public function testNormalizeValidationFailedException()
    {
        $this->normalizer->setSerializer(new Serializer([new ConstraintViolationListNormalizer()]));

        $expected = [
            'type' => 'https://symfony.com/errors/validation',
            'title' => 'Validation Failed',
            'status' => 422,
            'detail' => 'Invalid value',
            'violations' => [
                [
                    'propertyPath' => '',
                    'title' => 'Invalid value',
                    'template' => '',
                    'parameters' => [],
                ],
            ],
        ];

        $exception = new ValidationFailedException('Validation Failed', new ConstraintViolationList([new ConstraintViolation('Invalid value', '', [], '', null, null)]));
        $exception = new HttpException(422, 'Validation Failed', $exception);
        $this->assertSame($expected, $this->normalizer->normalize(FlattenException::createFromThrowable($exception), null, ['exception' => $exception]));
    }
}
