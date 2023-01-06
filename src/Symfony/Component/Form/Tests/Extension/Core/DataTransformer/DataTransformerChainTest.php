<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\DataTransformer\DataTransformerChain;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;

class DataTransformerChainTest extends TestCase
{
    public function testTransform()
    {
        $chain = new DataTransformerChain([
            new FixedDataTransformer(['foo' => 'bar']),
            new FixedDataTransformer(['bar' => 'baz']),
        ]);

        $this->assertEquals('baz', $chain->transform('foo'));
    }

    public function testReverseTransform()
    {
        $chain = new DataTransformerChain([
            new FixedDataTransformer(['baz' => 'bar']),
            new FixedDataTransformer(['bar' => 'foo']),
        ]);

        $this->assertEquals('baz', $chain->reverseTransform('foo'));
    }
}
