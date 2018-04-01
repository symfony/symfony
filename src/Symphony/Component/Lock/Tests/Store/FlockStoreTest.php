<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Lock\Tests\Store;

use Symphony\Component\Lock\Key;
use Symphony\Component\Lock\Store\FlockStore;

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
        return new FlockStore();
    }

    /**
     * @expectedException \Symphony\Component\Lock\Exception\InvalidArgumentException
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
     * @expectedException \Symphony\Component\Lock\Exception\InvalidArgumentException
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
            '%s/sf.-php-echo-hello-word-.%s.lock',
            sys_get_temp_dir(),
            strtr(substr(base64_encode(hash('sha256', $key, true)), 0, 7), '/', '_')
        );
        // ensure the file does not exist before the store
        @unlink($file);

        $store->save($key);

        $this->assertFileExists($file);

        $store->delete($key);
    }
}
