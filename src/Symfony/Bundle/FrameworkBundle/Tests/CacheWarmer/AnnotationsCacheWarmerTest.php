<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AnnotationsCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use Symfony\Component\Filesystem\Filesystem;

class AnnotationsCacheWarmerTest extends TestCase
{
    private $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/'.uniqid();
        $fs = new Filesystem();
        $fs->mkdir($this->cacheDir);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->cacheDir);
        parent::tearDown();
    }

    public function testAnnotationsCacheWarmerWithDebugDisabled()
    {
        file_put_contents($this->cacheDir.'/annotations.map', sprintf('<?php return %s;', var_export([__CLASS__], true)));
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
        file_put_contents($this->cacheDir.'/annotations.map', sprintf('<?php return %s;', var_export([__CLASS__], true)));
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
     * Test that the cache warming process is not broken if a class loader
     * throws an exception (on class / file not found for example).
     */
    public function testClassAutoloadException()
    {
        $this->assertFalse(class_exists($annotatedClass = 'C\C\C', false));

        file_put_contents($this->cacheDir.'/annotations.map', sprintf('<?php return %s;', var_export([$annotatedClass], true)));
        $warmer = new AnnotationsCacheWarmer(new AnnotationReader(), tempnam($this->cacheDir, __FUNCTION__));

        spl_autoload_register($classLoader = function ($class) use ($annotatedClass) {
            if ($class === $annotatedClass) {
                throw new \DomainException('This exception should be caught by the warmer.');
            }
        }, true, true);

        $warmer->warmUp($this->cacheDir);

        spl_autoload_unregister($classLoader);
    }

    /**
     * Test that the cache warming process is broken if a class loader throws an
     * exception but that is unrelated to the class load.
     */
    public function testClassAutoloadExceptionWithUnrelatedException()
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This exception should not be caught by the warmer.');

        $this->assertFalse(class_exists($annotatedClass = 'AClassThatDoesNotExist_FWB_CacheWarmer_AnnotationsCacheWarmerTest', false));

        file_put_contents($this->cacheDir.'/annotations.map', sprintf('<?php return %s;', var_export([$annotatedClass], true)));
        $warmer = new AnnotationsCacheWarmer(new AnnotationReader(), tempnam($this->cacheDir, __FUNCTION__));

        spl_autoload_register($classLoader = function ($class) use ($annotatedClass) {
            if ($class === $annotatedClass) {
                eval('class '.$annotatedClass.'{}');
                throw new \DomainException('This exception should not be caught by the warmer.');
            }
        }, true, true);

        $warmer->warmUp($this->cacheDir);

        spl_autoload_unregister($classLoader);
    }

    /**
     * @return MockObject|Reader
     */
    private function getReadOnlyReader(): object
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
