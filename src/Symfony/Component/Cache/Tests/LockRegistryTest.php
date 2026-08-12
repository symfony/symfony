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
    protected function tearDown(): void
    {
        LockRegistry::setEnabled(null);
        unset($_SERVER['APP_RUNTIME_MODE']);
    }

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

    public function testDisabledInCliMode()
    {
        $this->assertFalse(LockRegistry::isEnabled());
    }

    public function testEnabledInWebMode()
    {
        $_SERVER['APP_RUNTIME_MODE'] = 'web=1&worker=1';

        $this->assertTrue(LockRegistry::isEnabled());
    }

    public function testDisabledInCliRuntimeMode()
    {
        $_SERVER['APP_RUNTIME_MODE'] = 'web=0';

        $this->assertFalse(LockRegistry::isEnabled());
    }

    public function testSetEnabledOverridesRuntimeMode()
    {
        $_SERVER['APP_RUNTIME_MODE'] = 'web=0';

        $this->assertFalse(LockRegistry::setEnabled(true));
        $this->assertTrue(LockRegistry::isEnabled());
        $this->assertTrue(LockRegistry::setEnabled(null));
        $this->assertFalse(LockRegistry::isEnabled());
    }

    public function testComputeIsNotLockedWhenDisabled()
    {
        [$pool, $logger] = $this->createPool();

        $this->assertSame('bar', $pool->get('foo', static fn () => 'bar'));
        $this->assertSame([], $logger->messages);
    }

    public function testComputeIsLockedWhenEnabled()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('LockRegistry is disabled on Windows');
        }
        LockRegistry::setEnabled(true);
        [$pool, $logger] = $this->createPool();

        $this->assertSame('bar', $pool->get('foo', static fn () => 'bar'));
        $this->assertSame(['Lock acquired, now computing item "{key}"'], $logger->messages);
    }

    private function createPool(): array
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

        return [$pool, $logger];
    }
}
