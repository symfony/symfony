<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\Transformer\BuiltinTransformer;
use Symfony\Component\AutoMapper\Transformer\NullableTransformer;
use Symfony\Component\PropertyInfo\Type;

class NullableTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testNullTransformerTargetNullable()
    {
        $transformer = new NullableTransformer(new BuiltinTransformer(new Type('string'), [new Type('string', true)]), true);

        $output = $this->evalTransformer($transformer, 'foo');

        self::assertSame('foo', $output);

        $output = $this->evalTransformer($transformer, null);

        self::assertNull($output);
    }

    public function testNullTransformerTargetNotNullable()
    {
        $transformer = new NullableTransformer(new BuiltinTransformer(new Type('string'), [new Type('string')]), false);

        $output = $this->evalTransformer($transformer, 'foo');

        self::assertSame('foo', $output);
    }
}
