<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\LockRegistry;

class LockRegistryTest extends TestCase
{
    public function testFiles()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('LockRegistry is disabled on Windows');
        }
        $lockFiles = LockRegistry::setFiles([]);
        LockRegistry::setFiles($lockFiles);
        $expected = array_map('realpath', glob(__DIR__.'/../Adapter/*'));
        $this->assertSame($expected, $lockFiles);
    }
}
