<?php

namespace Symfony\Components\Validator\Mapping;

interface ClassMetadataFactoryInterface
{
    function getClassMetadata($class);
}