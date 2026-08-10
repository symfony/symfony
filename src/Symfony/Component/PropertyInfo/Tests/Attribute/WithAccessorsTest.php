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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Attribute\WithAccessors;
use Symfony\Component\PropertyInfo\Exception\LogicException;

class WithAccessorsTest extends TestCase
{
    public function testExceptionIsThrownWhenNoAccessorsAreDefined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('At least one of "getter", "setter", "adder", or "remover" must be defined.');

        new WithAccessors();
    }

    #[TestWith(['foo', null])]
    #[TestWith([null, 'foo'])]
    public function testExceptionIsThrownWhenOnlyAdderOrRemoverAreDefined(?string $adder, ?string $remover)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Both "adder" and "remover" must be defined when either is set.');

        new WithAccessors(adder: $adder, remover: $remover);
    }
}
