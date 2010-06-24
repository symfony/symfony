<?php

namespace Symfony\Tests\Components\Validator\Fixtures;

use Symfony\Components\Validator\Mapping\Loader\FilesLoader as BaseFilesLoader;
use Symfony\Components\Validator\Mapping\Loader\LoaderInterface;

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