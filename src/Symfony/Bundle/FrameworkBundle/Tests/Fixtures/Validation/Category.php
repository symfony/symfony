<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

class Category
{
    const NAME_PATTERN = '/\w+/';

    public $id;

    /**
     * @Assert\Type(self::NAME_PATTERN)
     */
    public $name;
}
