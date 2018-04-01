<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\CallbackTransformer;

class CallbackTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new CallbackTransformer(
            function ($value) { return $value.' has been transformed'; },
            function ($value) { return $value.' has reversely been transformed'; }
        );

        $this->assertEquals('foo has been transformed', $transformer->transform('foo'));
        $this->assertEquals('bar has reversely been transformed', $transformer->reverseTransform('bar'));
    }
}
