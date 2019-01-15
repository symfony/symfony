<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\CallbackTransformer;

class CallbackTransformerTest extends TestCase
{
    /**
     * @dataProvider transformProvider
     */
    public function testTransform($expected, ?callable $transform, $value): void
    {
        $this->assertSame($expected, (new CallbackTransformer($transform, null))->transform($value));
    }

    public function transformProvider(): array
    {
        return [
            ['foo has been transformed', function ($value) { return $value.' has been transformed'; }, 'foo'],
            ['ccc', null, 'ccc'],
        ];
    }

    /**
     * @dataProvider reverseTransformProvider
     */
    public function testReverseTransform($expected, ?callable $reverseTransform, $value): void
    {
        $this->assertSame($expected, (new CallbackTransformer(null, $reverseTransform))->reverseTransform($value));
    }

    public function reverseTransformProvider(): array
    {
        return [
          ['bar has reversely been transformed', function ($value) { return $value.' has reversely been transformed'; }, 'bar'],
          ['ccc', null, 'ccc'],
        ];
    }
}
