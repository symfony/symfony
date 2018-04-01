<?php

namespace Symphony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

// Missing "use" for Assert\Type is on purpose

class SubCategory extends Category
{
    /**
     * @Assert\Type("string")
     */
    public $main;
}
