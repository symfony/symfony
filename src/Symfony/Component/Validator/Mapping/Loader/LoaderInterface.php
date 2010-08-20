<?php

namespace Symfony\Component\Validator\Mapping\Loader;

use Symfony\Component\Validator\Mapping\ClassMetadata;

interface LoaderInterface
{
    /**
     * @param  ClassMetadata $metadata
     * @return boolean
     */
    function loadClassMetadata(ClassMetadata $metadata);
}