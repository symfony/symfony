<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures;

#[\Attribute(\Attribute::TARGET_CLASS)]
#[PrivateFCC(self::testMethod(...))]
class PrivateFCC
{
    private static function testMethod()
    {
    }
}
