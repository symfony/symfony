<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\SemaphoreStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension sysvsem
 */
class SemaphoreStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getStore()
    {
        if (\PHP_VERSION_ID < 50601) {
            $this->markTestSkipped('Non blocking semaphore are supported by PHP version greater or equals than 5.6.1');
        }

        return new SemaphoreStore();
    }

    public function testResourceRemoval()
    {
        $initialCount = $this->getOpenedSemaphores();
        $store = new SemaphoreStore();
        $key = new Key(uniqid(__METHOD__, true));
        $store->waitAndSave($key);

        $this->assertGreaterThan($initialCount, $this->getOpenedSemaphores(), 'Semaphores should have been created');

        $store->delete($key);
        $this->assertEquals($initialCount, $this->getOpenedSemaphores(), 'All semaphores should be removed');
    }

    private function getOpenedSemaphores()
    {
        $lines = explode(PHP_EOL, trim(`ipcs -su`));
        if ('------ Semaphore Status --------' !== $lines[0]) {
            throw new \Exception('Failed to extract list of opend semaphores. Expect a Semaphore status, got '.implode(PHP_EOL, $lines));
        }
        list($key, $value) = explode(' = ', $lines[1]);
        if ('used arrays' !== $key) {
            throw new \Exception('Failed to extract list of opend semaphores. Expect a used arrays key, got '.implode(PHP_EOL, $lines));
        }

        return (int) $value;
    }
}
