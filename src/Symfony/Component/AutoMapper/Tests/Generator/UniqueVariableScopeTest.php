<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;

class UniqueVariableScopeTest extends TestCase
{
    public function testVariableNameNotEquals()
    {
        $uniqueVariable = new UniqueVariableScope();
        $var1 = $uniqueVariable->getUniqueName('value');
        $var2 = $uniqueVariable->getUniqueName('value');
        $var3 = $uniqueVariable->getUniqueName('VALUE');

        self::assertNotSame($var1, $var2);
        self::assertNotSame($var1, $var3);
        self::assertNotSame($var2, $var3);
        self::assertNotSame(strtolower($var1), strtolower($var3));
    }
}
