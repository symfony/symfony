<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FooType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setAttribute('foo', 'x');
        $builder->setAttribute('data_option', $options['data']);
    }

    public function getName()
    {
        return 'foo';
    }

    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return new FormBuilder($name, null, new EventDispatcher(), $factory);
    }

    public function getDefaultOptions()
    {
        return array(
            'data' => null,
            'required' => false,
            'max_length' => null,
            'a_or_b' => 'a',
        );
    }

    public function getAllowedOptionValues()
    {
        return array(
            'a_or_b' => array('a', 'b'),
        );
    }

    public function getParent()
    {
        return null;
    }
}
