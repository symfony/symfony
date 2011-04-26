<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

abstract class AbstractType implements FormTypeInterface
{
    private $extensions = array();

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
        preg_match('/\\\\(\w+?)(Form)?(Type)?$/i', get_class($this), $matches);

        return strtolower($matches[1]);
    }

    public function setExtensions(array $extensions)
    {
        foreach ($extensions as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormTypeExtensionInterface');
            }
        }

        $this->extensions = $extensions;
    }

    public function getExtensions()
    {
        return $this->extensions;
    }
}
