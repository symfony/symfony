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

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class FlockStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;
    use UnserializableTestTrait;

    protected function getStore(): PersistingStoreInterface
    {
        return new FlockStore();
    }

    public function testConstructWhenRepositoryCannotBeCreated()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The FlockStore directory "/a/b/c/d/e" does not exists and cannot be created.');
        if (!getenv('USER') || 'root' === getenv('USER')) {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        new FlockStore('/a/b/c/d/e');
    }

    public function testConstructWhenRepositoryIsNotWriteable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The FlockStore directory "/" is not writable.');
        if (!getenv('USER') || 'root' === getenv('USER')) {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        new FlockStore('/');
    }

    public function testConstructWithSubdir()
    {
        new FlockStore($dir = (sys_get_temp_dir().'/sf-flock'));
        $this->assertDirectoryExists($dir);
        // cleanup
        @rmdir($dir);
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

    public function testSaveSanitizeLongName()
    {
        $store = $this->getStore();

        $key = new Key(str_repeat(__CLASS__, 100));

        $file = sprintf(
            '%s/sf.Symfony-Component-Lock-Tests-Store-FlockStoreTestS.%s.lock',
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
