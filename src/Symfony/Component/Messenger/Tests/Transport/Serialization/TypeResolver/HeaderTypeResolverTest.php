<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization\TypeResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\TypeResolver\HeaderTypeResolver;

final class HeaderTypeResolverTest extends TestCase
{
    public function testResolverWithTypeHeader()
    {
        $sut = new HeaderTypeResolver('class_type');

        $class = $sut->resolve(['headers' => ['class_type' => 'App\MyClass']]);
        $this->assertSame('App\MyClass', $class);
    }

    public function provideResolverWithoutTypeHeaderShouldThrowException(): array
    {
        return [
            [['headers' => []]],
            [[]],
        ];
    }

    /**
     * @dataProvider provideResolverWithoutTypeHeaderShouldThrowException
     */
    public function testResolverWithoutTypeHeaderShouldThrowException(array $encodedEnvelope)
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Encoded envelope does not have a "class_type" header.');

        $sut = new HeaderTypeResolver('class_type');
        $sut->resolve($encodedEnvelope);
    }
}
