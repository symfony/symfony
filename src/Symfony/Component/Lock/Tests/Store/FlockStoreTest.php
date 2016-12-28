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
use Symfony\Component\Lock\Store\FlockStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class FlockStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getStore()
    {
        return new FlockStore(sys_get_temp_dir());
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\InvalidArgumentException
     * @expectedExceptionMessage The directory "/a/b/c/d/e" is not writable.
     */
    public function testConstructWhenRepositoryDoesNotExist()
    {
        if (!getenv('USER') || 'root' === getenv('USER')) {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        new FlockStore('/a/b/c/d/e');
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\InvalidArgumentException
     * @expectedExceptionMessage The directory "/" is not writable.
     */
    public function testConstructWhenRepositoryIsNotWriteable()
    {
        if (!getenv('USER') || 'root' === getenv('USER')) {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        new FlockStore('/');
    }

    public function testSaveSanitizeName()
    {
        $store = $this->getStore();

        $key = new Key('<?php echo "% hello word ! %" ?>');

        $file = sprintf(
            '%s/sf.-php-echo-hello-word-.4b3d9d0d27ddef3a78a64685dda3a963e478659a9e5240feaf7b4173a8f28d5f.lock',
            sys_get_temp_dir()
        );
        // ensure the file does not exist before the store
        @unlink($file);

        $store->save($key);

        $this->assertFileExists($file);

        $store->delete($key);
    }
}
