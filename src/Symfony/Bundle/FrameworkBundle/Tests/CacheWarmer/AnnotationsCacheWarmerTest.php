<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AnnotationsCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use Symfony\Component\Filesystem\Filesystem;

class AnnotationsCacheWarmerTest extends TestCase
{
    private $cacheDir;

    protected function setUp()
    {
        $this->cacheDir = sys_get_temp_dir().'/'.uniqid();
        $fs = new Filesystem();
        $fs->mkdir($this->cacheDir);
        parent::setUp();
    }

    protected function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove($this->cacheDir);
        parent::tearDown();
    }

    public function testAnnotationsCacheWarmerWithDebugDisabled()
    {
        file_put_contents($this->cacheDir.'/annotations.map', sprintf('<?php return %s;', var_export(array(__CLASS__), true)));
        $cacheFile = tempnam($this->cacheDir, __FUNCTION__);
        $reader = new AnnotationReader();
        $warmer = new AnnotationsCacheWarmer($reader, $cacheFile);
        $warmer->warmUp($this->cacheDir);
        $this->assertFileExists($cacheFile);

        // Assert cache is valid
        $reader = new CachedReader(
            $this->getReadOnlyReader(),
            new DoctrineProvider(new PhpArrayAdapter($cacheFile, new NullAdapter()))
        );
        $refClass = new \ReflectionClass($this);
        $reader->getClassAnnotations($refClass);
        $reader->getMethodAnnotations($refClass->getMethod(__FUNCTION__));
        $reader->getPropertyAnnotations($refClass->getProperty('cacheDir'));
    }

    public function testAnnotationsCacheWarmerWithDebugEnabled()
    {
        file_put_contents($this->cacheDir.'/annotations.map', sprintf('<?php return %s;', var_export(array(__CLASS__), true)));
        $cacheFile = tempnam($this->cacheDir, __FUNCTION__);
        $reader = new AnnotationReader();
        $warmer = new AnnotationsCacheWarmer($reader, $cacheFile, null, true);
        $warmer->warmUp($this->cacheDir);
        $this->assertFileExists($cacheFile);
        // Assert cache is valid
        $reader = new CachedReader(
            $this->getReadOnlyReader(),
            new DoctrineProvider(new PhpArrayAdapter($cacheFile, new NullAdapter())),
            true
        );
        $refClass = new \ReflectionClass($this);
        $reader->getClassAnnotations($refClass);
        $reader->getMethodAnnotations($refClass->getMethod(__FUNCTION__));
        $reader->getPropertyAnnotations($refClass->getProperty('cacheDir'));
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Reader
     */
    private function getReadOnlyReader()
    {
        $readerMock = $this->getMockBuilder('Doctrine\Common\Annotations\Reader')->getMock();
        $readerMock->expects($this->exactly(0))->method('getClassAnnotations');
        $readerMock->expects($this->exactly(0))->method('getClassAnnotation');
        $readerMock->expects($this->exactly(0))->method('getMethodAnnotations');
        $readerMock->expects($this->exactly(0))->method('getMethodAnnotation');
        $readerMock->expects($this->exactly(0))->method('getPropertyAnnotations');
        $readerMock->expects($this->exactly(0))->method('getPropertyAnnotation');

        return $readerMock;
    }
}
