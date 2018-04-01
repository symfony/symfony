<?php

namespace Symphony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

use Symphony\Component\Validator\Constraints as Assert;

class Category
{
    const NAME_PATTERN = '/\w+/';

    public $id;

    /**
     * @Assert\Type("string")
     */
    public $name;
}
