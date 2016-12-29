<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

use Symfony\Component\Validator\Constraints as Assert;

class Category
{
    const NAME_PATTERN = '/\w+/';

    public $id;

    /**
     * @Assert\Type(Category::NAME_PATTERN)
     */
    public $name;
}
