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
use Symfony\Component\AutoMapper\Transformer\ArrayTransformer;
use Symfony\Component\AutoMapper\Transformer\BuiltinTransformer;
use Symfony\Component\PropertyInfo\Type;

class ArrayTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testArrayToArray(): void
    {
        $transformer = new ArrayTransformer(new BuiltinTransformer(new Type('string'), [new Type('string')]));
        $output = $this->evalTransformer($transformer, ['test']);

        self::assertEquals(['test'], $output);
    }
}
