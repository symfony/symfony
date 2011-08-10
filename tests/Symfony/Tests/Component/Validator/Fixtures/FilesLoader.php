<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Mapping\Loader\FilesLoader as BaseFilesLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

abstract class FilesLoader extends BaseFilesLoader
{
    protected $timesCalled = 0;
    protected $loader;

    public function __construct(array $paths, LoaderInterface $loader)
    {
        $this->loader = $loader;
        parent::__construct($paths);
    }

    protected function getFileLoaderInstance($file)
    {
        $this->timesCalled++;

        return $this->loader;
    }

    public function getTimesCalled()
    {
        return $this->timesCalled;
    }
}
