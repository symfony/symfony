<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;

abstract class AbstractType implements FormTypeInterface
{
    public function buildForm(FormBuilder $builder, array $options)
    {
    }

    public function buildView(FormView $view, FormInterface $form)
    {
    }

    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
    }

    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return null;
    }

    public function getDefaultOptions(array $options)
    {
        return array();
    }

    public function getParent(array $options)
    {
        return 'form';
    }

    public function getName()
    {
        if (preg_match('/\\\\([a-z]+)(?:Form|Type)$/im', get_class($this), $matches)) {
            $name = strtolower($matches[1]);
        }

        return $name;
    }
}
