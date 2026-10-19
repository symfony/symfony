<?php

namespace Symfony\Component\Validator\Tests\Fixtures\Validation;

// Assert\Type does not exist on purpose
use Symfony\Component\Validator\Tests\Fixtures\Constraints as Assert;

class SubCategory extends Category
{
    #[Assert\Type("string")]
    public $main;
}
