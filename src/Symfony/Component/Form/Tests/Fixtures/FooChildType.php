<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;

class FooChildType extends AbstractType
{

    public function getName()
    {
        return 'foo_child';
    }

    public function getParent(array $options)
    {
        return new FooType();
    }

    public function getDefaultOptions()
    {
        return array(
            'parent' => new FooParentType()
        );
    }
}
