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
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\SemaphoreStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension sysvsem
 */
class SemaphoreStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use UnserializableTestTrait;

    protected function getStore(): PersistingStoreInterface
    {
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
        if ('Darwin' === \PHP_OS) {
            $lines = explode(\PHP_EOL, trim(shell_exec('ipcs -s')));
            if (-1 === $start = array_search('Semaphores:', $lines)) {
                throw new \Exception('Failed to extract list of opened semaphores. Expected a Semaphore list, got '.implode(\PHP_EOL, $lines));
            }

            return \count(\array_slice($lines, ++$start));
        }

        $lines = explode(\PHP_EOL, trim(shell_exec('LC_ALL=C ipcs -su')));
        if ('------ Semaphore Status --------' !== $lines[0]) {
            throw new \Exception('Failed to extract list of opened semaphores. Expected a Semaphore status, got '.implode(\PHP_EOL, $lines));
        }
        [$key, $value] = explode(' = ', $lines[1]);
        if ('used arrays' !== $key) {
            throw new \Exception('Failed to extract list of opened semaphores. Expected a "used arrays" key, got '.implode(\PHP_EOL, $lines));
        }

        return (int) $value;
    }
}
