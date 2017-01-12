<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

use Symfony\Component\Validator\Constraints as Assert;

class SubCategory extends Category
{
    /**
     * @Assert\Type(Category::class)
     */
    public $main;
}
