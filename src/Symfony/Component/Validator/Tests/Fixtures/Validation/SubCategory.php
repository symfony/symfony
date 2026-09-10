<?php

namespace Symfony\Component\Validator\Tests\Fixtures\Validation;

// Missing "use" for Assert\Type is on purpose

class SubCategory extends Category
{
    #[Assert\Type("string")]
    public $main;
}
