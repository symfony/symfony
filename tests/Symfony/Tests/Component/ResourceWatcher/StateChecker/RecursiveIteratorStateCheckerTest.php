<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ResourceWatcher\StateChecker;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\ResourceWatcher\StateChecker\RecursiveIteratorStateChecker;

class RecursiveIteratorStateCheckerTest extends \PHPUnit_Framework_TestCase
{
    private $tempPath;

    protected function setUp()
    {
        $this->tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'symfony_resource_watcher_recucrisve_statechecker';

        if (!file_exists($this->tempPath)) {
            mkdir($this->tempPath, 0777, true);
        }
    }

    protected function tearDown()
    {
        if (file_exists($this->tempPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempPath), \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $path) {
                if ($path->isDir()) {
                    rmdir((string) $path);
                } else {
                    unlink((string) $path);
                }
            }

            rmdir($this->tempPath);
        }
    }

    public function testFileStatusChecks()
    {
        touch($path = $this->generateRandomPath(), time() - 10);
        $resource   = new FileResource($path);
        $checker    = new RecursiveIteratorStateChecker($resource);

        $this->assertTrue($checker->isResourceExists());
        $this->assertEquals(0, count($checker->checkChanges()));

        touch($path, time() - 2);
        filemtime($path);

        $this->assertEquals(array('modified' => array($resource)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));
        $this->assertEquals(0, count($checker->checkChanges()));

        file_put_contents($path, 'test2');
        filemtime($path);

        $this->assertEquals(array('modified' => array($resource)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        unlink($path);

        $this->assertTrue($checker->isResourceExists());
        $this->assertEquals(array('deleted' => array($resource)), $checker->checkChanges());
        $this->assertFalse($checker->isResourceExists());
        $this->assertEquals(0, count($checker->checkChanges()));
        $this->assertEquals(0, count($checker->checkChanges()));
    }

    public function testDirectoryStatusChecks()
    {
        mkdir($path = $this->generateRandomPath(), 0777, true);

        $resource   = new DirectoryResource($path);
        $checker    = new RecursiveIteratorStateChecker($resource);

        $this->assertTrue($checker->isResourceExists());
        $this->assertEquals(0, count($checker->checkChanges()));

        touch($filePath = $this->generateRandomPath($path), time() - 10);
        $file1 = new FileResource($filePath);
        filemtime($file1->getResource());

        $this->assertEquals(array('created' => array($file1)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        touch($file1->getResource(), time() - 5);
        filemtime($file1->getResource());

        $this->assertEquals(array('modified' => array($file1)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        touch($filePath = $this->generateRandomPath($path), time() - 10);
        $file2 = new FileResource($filePath);
        filemtime($file2->getResource());

        $this->assertEquals(array('created' => array($file2)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        unlink($file1->getResource());

        $this->assertEquals(array('deleted' => array($file1)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));
        $this->assertTrue($checker->isResourceExists());

        touch($file2->getResource());
        filemtime($file2->getResource());

        $this->assertEquals(array('modified' => array($file2)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        unlink($file2->getResource());

        $this->assertEquals(array('deleted' => array($file2)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));
        $this->assertTrue($checker->isResourceExists());

        rmdir($resource->getResource());
        $this->assertEquals(array('deleted' => array($resource)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));
        $this->assertFalse($checker->isResourceExists());
    }

    public function testDirectoryInsideDirectoryChecks()
    {
        mkdir($path = $this->generateRandomPath(), 0777, true);
        $resource   = new DirectoryResource($path);
        $checker    = new RecursiveIteratorStateChecker($resource);

        $this->assertTrue($checker->isResourceExists());
        $this->assertEquals(0, count($checker->checkChanges()));

        mkdir($dir1Path = $this->generateRandomPath($path), 0777, true);
        $dir1 = new DirectoryResource($dir1Path);

        $this->assertEquals(array('created' => array($dir1)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        mkdir($dir2Path = $this->generateRandomPath($dir1Path), 0777, true);
        $dir2 = new DirectoryResource($dir2Path);

        $this->assertEquals(array('created' => array($dir2)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        touch($filePath = $this->generateRandomPath($dir1Path), time() - 10);
        $file1 = new FileResource($filePath);
        filemtime($file1->getResource());

        $this->assertEquals(array('created' => array($file1)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        touch($filePath = $this->generateRandomPath($dir2Path), time() - 10);
        $file2 = new FileResource($filePath);
        filemtime($file1->getResource());

        $this->assertEquals(array('created' => array($file2)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));

        unlink($file1->getResource());

        $this->assertEquals(array('deleted' => array($file1)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));
        $this->assertTrue($checker->isResourceExists());

        touch($file2, time() - 5);
        filemtime($file2->getResource());

        $this->assertEquals(array('modified' => array($file2)), $checker->checkChanges());
        $this->assertEquals(0, count($checker->checkChanges()));
    }

    private function generateRandomPath($prefix = null)
    {
        if (null === $prefix) {
            $prefix = $this->tempPath;
        }

        return $prefix . DIRECTORY_SEPARATOR . md5(microtime(true) . rand(1, 9999));
    }
}
