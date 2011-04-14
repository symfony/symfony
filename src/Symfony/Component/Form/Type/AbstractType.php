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
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\TemplateContext;

abstract class AbstractType implements FormTypeInterface
{
    public function buildForm(FormBuilder $builder, array $options)
    {
    }

    public function buildContext(TemplateContext $context, FormInterface $form)
    {
    }

    public function buildContextBottomUp(TemplateContext $context, FormInterface $form)
    {
    }

    public function createBuilder($name, array $options)
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
        return get_class($this);
    }
}