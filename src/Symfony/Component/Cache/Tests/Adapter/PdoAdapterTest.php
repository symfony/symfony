<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

#[RequiresPhpExtension('pdo_sqlite')]
#[Group('time-sensitive')]
class PdoAdapterTest extends AbstractPdoAdapterTestCase
{
    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new PdoAdapter('sqlite:'.self::$dbFile, 'ns', $defaultLifetime);
    }

    public function testShutdownResetKeepsAConnectionPassedToTheConstructor()
    {
        $cache = new PdoAdapter($pdo = new \PDO('sqlite:'.self::$dbFile));
        $cache->reset(true);

        $this->assertSame($pdo, (new \ReflectionMethod($cache, 'getConnection'))->invoke($cache));
    }

    public function testOptionsAreNotExposedInTrace()
    {
        if (!class_exists(\SensitiveParameterValue::class)) {
            $this->markTestSkipped('Sensitive parameter support requires PHP 8.2.');
        }

        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            new PdoAdapter('mysql://localhost/db', '', 0, ['db_password' => 's3cr3t']);
            $this->fail('An exception should have been thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringNotContainsString('s3cr3t', json_encode($e->getTrace(), \JSON_THROW_ON_ERROR));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
