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
use Symfony\Component\Form\Renderer\FormRendererInterface;

abstract class AbstractType implements FormTypeInterface
{
    public function configure(FormBuilder $builder, array $options)
    {
    }

    public function buildRenderer(FormRendererInterface $renderer, FormInterface $form)
    {
    }

    public function buildRendererBottomUp(FormRendererInterface $renderer, FormInterface $form)
    {
    }

    public function createBuilder(array $options)
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