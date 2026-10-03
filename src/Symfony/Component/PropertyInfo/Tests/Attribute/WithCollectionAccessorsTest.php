<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Attribute\WithCollectionAccessors;
use Symfony\Component\PropertyInfo\Exception\LogicException;

class WithCollectionAccessorsTest extends TestCase
{
    public function testExceptionIsThrownWhenAdderIsEmpty()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "adder" argument must not be empty.');

        new WithCollectionAccessors('', 'removeElement');
    }
}
