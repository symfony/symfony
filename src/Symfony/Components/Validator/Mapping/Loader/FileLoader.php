<?php

namespace Symfony\Components\Validator\Mapping\Loader;

use Symfony\Components\Validator\Exception\MappingException;
use Symfony\Components\Validator\Mapping\ClassMetadata;
use Symfony\Components\Validator\Mapping\GroupMetadata;

abstract class FileLoader implements LoaderInterface
{
    protected $file;

    public function __construct($file)
    {
        if (!file_exists($file)) {
            throw new MappingException(sprintf('The mapping file %s does not exist', $file));
        }

        if (!is_readable($file)) {
            throw new MappingException(sprintf('The mapping file %s is not readable', $file));
        }

        $this->file = $file;
    }
}