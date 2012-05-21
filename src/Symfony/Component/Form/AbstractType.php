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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

abstract class AbstractType implements FormTypeInterface
{
    /**
     * The extensions for this type
     * @var array An array of FormTypeExtensionInterface instances
     */
    private $extensions = array();

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedOptionValues()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(array $options)
    {
        return 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function setExtensions(array $extensions)
    {
        foreach ($extensions as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormTypeExtensionInterface');
            }
        }

        $this->extensions = $extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
}
