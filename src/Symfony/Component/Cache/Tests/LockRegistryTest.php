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
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
        $expected = array_map('realpath', glob(__DIR__.'/../Adapter/*.php'));
        $this->assertSame($expected, $lockFiles);
    }

    public function testLockIsDisabledOnCli()
    {
        $logger = new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = $message;
            }
        };

        $pool = new FilesystemAdapter('lock-registry', 0, sys_get_temp_dir().'/symfony-cache-lock-registry');
        $pool->clear();
        $pool->setLogger($logger);

        $this->assertSame('bar', $pool->get('foo', static fn () => 'bar'));
        $this->assertSame([], $logger->messages);
    }
}
