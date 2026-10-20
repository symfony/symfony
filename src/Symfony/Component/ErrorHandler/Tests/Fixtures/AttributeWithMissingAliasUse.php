<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

class AttributeWithMissingAliasUse
{
    #[Assert\NotBlank]
    public $prop;
}
