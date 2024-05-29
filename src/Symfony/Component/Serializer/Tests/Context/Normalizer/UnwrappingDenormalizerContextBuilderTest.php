<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\Normalizer\UnwrappingDenormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class UnwrappingDenormalizerContextBuilderTest extends TestCase
{
    private UnwrappingDenormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new UnwrappingDenormalizerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withUnwrapPath($values[UnwrappingDenormalizer::UNWRAP_PATH])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            UnwrappingDenormalizer::UNWRAP_PATH => 'foo',
        ]];

        yield 'With null values' => [[
            UnwrappingDenormalizer::UNWRAP_PATH => null,
        ]];
    }

    public function testCannotSetInvalidPropertyPath()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->contextBuilder->withUnwrapPath('invalid path...');
    }
}
