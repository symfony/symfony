<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\OutputEscaper;

use Symfony\Component\OutputEscaper\SafeDecorator;

class SafeDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetRawValue()
    {
        $safe = new SafeDecorator('foo');
        $this->assertEquals('foo', $safe->getRawValue(), '->getValue() returns the embedded value');
    }
}
