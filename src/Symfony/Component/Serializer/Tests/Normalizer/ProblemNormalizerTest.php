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
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;

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
}
