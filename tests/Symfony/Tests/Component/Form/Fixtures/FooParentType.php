<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\AbstractType;

class FooParentType extends AbstractType
{
    public function getName()
    {
        return 'foo_parent';
    }

    public function getParent(array $options)
    {
        return null;
    }
}