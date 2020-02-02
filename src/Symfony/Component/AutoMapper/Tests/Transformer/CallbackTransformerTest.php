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
use Symfony\Component\AutoMapper\Transformer\CallbackTransformer;

class CallbackTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testCallbackTransform()
    {
        $transformer = new CallbackTransformer('test');
        $function = $this->createTransformerFunction($transformer);
        $class = new class () {
            public $callbacks;

            public function __construct()
            {
                $this->callbacks['test'] = function ($input) {
                    return 'output';
                };
            }
        };

        $transform = \Closure::bind($function, $class);

        $output = $transform('input');

        self::assertEquals('output', $output);
    }
}
