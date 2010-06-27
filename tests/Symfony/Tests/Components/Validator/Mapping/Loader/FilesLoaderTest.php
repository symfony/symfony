<?php

namespace Symfony\Tests\Components\Validator\Mapping\Loader;

require_once __DIR__.'/../../Fixtures/FilesLoader.php';
require_once __DIR__.'/../../Fixtures/Entity.php';

use Symfony\Components\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Components\Validator\Mapping\ClassMetadata;

class FilesLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testCallsGetFileLoaderInstanceForeachPath()
    {
        $loader = $this->getFilesLoader($this->getFileLoader());
        $this->assertEquals(4, $loader->getTimesCalled());
    }

    public function testCallsActualFileLoaderForMetadata()
    {
        $fileLoader = $this->getFileLoader();
        $fileLoader->expects($this->exactly(4))
            ->method('loadClassMetadata');
        $loader = $this->getFilesLoader($fileLoader);
        $loader->loadClassMetadata(new ClassMetadata('Symfony\Tests\Components\Validator\Fixtures\Entity'));
    }

    public function getFilesLoader(LoaderInterface $loader)
    {
        return $this->getMockForAbstractClass('Symfony\Tests\Components\Validator\Fixtures\FilesLoader', array(array(
            __DIR__ . '/constraint-mapping.xml',
            __DIR__ . '/constraint-mapping.yaml',
            __DIR__ . '/constraint-mapping.test',
            __DIR__ . '/constraint-mapping.txt',
        ), $loader));
    }

    public function getFileLoader()
    {
        return $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
    }
}