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
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractType implements FormTypeInterface
{
    /**
     * @var array
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    private $extensions = array();

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults($this->getDefaultOptions(array()));
        $resolver->addAllowedValues($this->getAllowedOptionValues(array()));
    }

    /**
     * Returns the default options for this type.
     *
     * @param array $options Unsupported as of Symfony 2.1.
     *
     * @return array The default options
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     *             Use {@link setDefaultOptions()} instead.
     */
    public function getDefaultOptions(array $options)
    {
        return array();
    }

    /**
     * Returns the allowed option values for each option (if any).
     *
     * @param array $options Unsupported as of Symfony 2.1.
     *
     * @return array The allowed option values
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     *             Use {@link setDefaultOptions()} instead.
     */
    public function getAllowedOptionValues(array $options)
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'form';
    }

    /**
     * Sets the extensions for this type.
     *
     * @param array $extensions An array of FormTypeExtensionInterface
     *
     * @throws Exception\UnexpectedTypeException if any extension does not implement FormTypeExtensionInterface
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * Returns the extensions associated with this type.
     *
     * @return array An array of FormTypeExtensionInterface
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link ResolvedFormTypeInterface::getTypeExtensions()} instead.
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
}
