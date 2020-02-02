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
use Symfony\Component\AutoMapper\Transformer\MultipleTransformer;
use Symfony\Component\PropertyInfo\Type;

class MultipleTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testMultipleTransformer()
    {
        $transformer = new MultipleTransformer([
            [
                'transformer' => new BuiltinTransformer(new Type('string'), [new Type('int')]),
                'type' => new Type('string'),
            ],
            [
                'transformer' => new BuiltinTransformer(new Type('int'), [new Type('string')]),
                'type' => new Type('int'),
            ],
        ]);

        $output = $this->evalTransformer($transformer, '12');

        self::assertSame(12, $output);

        $output = $this->evalTransformer($transformer, 12);

        self::assertSame('12', $output);
    }
}
