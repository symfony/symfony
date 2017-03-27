<?php

namespace Symfony\Component\Image\Tests\Regression;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Imagick\Loader as ImagickLoader;
use Symfony\Component\Image\Gmagick\Loader as GmagickLoader;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Tests\TestCase;

class RegressionSaveTest extends TestCase
{
    private function getTemporaryDir()
    {
        $tempDir = tempnam(sys_get_temp_dir(), 'symfony-image');

        unlink($tempDir);
        mkdir($tempDir);

        return $tempDir;
    }

    private function getDirContent($dir)
    {
        $filenames = array();

        foreach (new \DirectoryIterator($dir) as $fileinfo) {
            if ($fileinfo->isFile()) {
                $filenames[] = $fileinfo->getPathname();
            }
        }

        return $filenames;
    }

    private function getImagickLoader($file)
    {
        try {
            $loader = new ImagickLoader();
            $image = $loader->open($file);
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        return $image;
    }

    private function getGmagickLoader($file)
    {
        try {
            $loader = new GmagickLoader();
            $image = $loader->open($file);
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        return $image;
    }

    public function testShouldSaveOneFileWithImagick()
    {
        $dir = realpath($this->getTemporaryDir());
        $targetFile = $dir.'/myfile.png';

        $loader = $this->getImagickLoader(FixturesLoader::getFixture('multi-layer.psd'));

        $loader->save($targetFile);

        if (!$this->probeOneFileAndCleanup($dir, $targetFile)) {
            $this->fail('Imagick failed to generate one file');
        }
    }

    public function testShouldSaveOneFileWithGmagick()
    {
        $dir = realpath($this->getTemporaryDir());
        $targetFile = $dir.'/myfile.png';

        $loader = $this->getGmagickLoader(FixturesLoader::getFixture('multi-layer.psd'));

        $loader->save($targetFile);

        if (!$this->probeOneFileAndCleanup($dir, $targetFile)) {
            $this->fail('Gmagick failed to generate one file');
        }
    }

    private function probeOneFileAndCleanup($dir, $targetFile)
    {
        $this->assertFileExists($targetFile);

        $retval = true;
        $files = $this->getDirContent($dir);
        $retval = $retval && count($files) === 1;
        $file = current($files);
        $retval = $retval && $targetFile === $file;

        foreach ($files as $file) {
            unlink($file);
        }

        rmdir($dir);

        return $retval;
    }
}
