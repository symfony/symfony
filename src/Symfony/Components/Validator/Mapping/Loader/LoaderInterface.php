<?php

namespace Symfony\Components\Validator\Mapping\Loader;

use Symfony\Components\Validator\Mapping\ClassMetadata;

interface LoaderInterface
{
    /**
     * @param  ClassMetadata $metadata
     * @return boolean
     */
    function loadClassMetadata(ClassMetadata $metadata);
}