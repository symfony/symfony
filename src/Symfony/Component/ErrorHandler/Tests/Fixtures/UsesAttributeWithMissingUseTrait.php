<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

class UsesAttributeWithMissingUseTrait
{
    use AttributeWithMissingUseTrait {
        traitMethod as aliasedTraitMethod;
    }

    #[MissingOwnPropertyAttribute]
    public $traitProperty;

    #[MissingOwnMethodAttribute]
    public function traitMethod()
    {
    }
}
