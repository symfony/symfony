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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

class AbstractAdapterCreateConnectionTest extends TestCase
{
    public function testOptionsAreNotExposedInTrace()
    {
        if (!class_exists(\SensitiveParameterValue::class)) {
            $this->markTestSkipped('Sensitive parameter support requires PHP 8.2.');
        }

        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            foreach (['redis://localhost:999999', 'memcached://localhost:999999', 'couchbase://localhost:999999/cache'] as $dsn) {
                try {
                    AbstractAdapter::createConnection($dsn, ['auth' => 's3cr3t', 'password' => 's3cr3t']);
                    $this->fail('An exception should have been thrown.');
                } catch (CacheException|InvalidArgumentException $e) {
                    $this->assertStringNotContainsString('s3cr3t', json_encode($e->getTrace(), \JSON_THROW_ON_ERROR));
                }
            }
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
