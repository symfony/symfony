<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\AbstractType;

class FooChildType extends AbstractType
{
    public function getName()
    {
        return 'foo_child';
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'parent' => new FooParentType(),
        );
    }

    public function getParent(array $options)
    {
        return new FooType();
    }
}