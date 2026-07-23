<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Fixtures;

class ClassStringFixture
{
    public static string $publicStatic = 'public value';
    protected static int $protectedStatic = 42;
    private static bool $privateStatic = true;
    public static string $notInitialized;
}
