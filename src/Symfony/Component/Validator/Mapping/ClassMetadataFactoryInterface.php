<?php

namespace Symfony\Component\Validator\Mapping;

interface ClassMetadataFactoryInterface
{
    function getClassMetadata($class);
}