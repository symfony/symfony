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
        $defaults = $this->getDefaultOptions(array());
        $allowedTypes = $this->getAllowedOptionValues(array());

        if (!empty($defaults)) {
            trigger_error('getDefaultOptions() is deprecated since version 2.1 and will be removed in 2.3. Use setDefaultOptions() instead.', E_USER_DEPRECATED);

            $resolver->setDefaults($defaults);
        }

        if (!empty($allowedTypes)) {
            trigger_error('getAllowedOptionValues() is deprecated since version 2.1 and will be removed in 2.3. Use setDefaultOptions() instead.', E_USER_DEPRECATED);

            $resolver->addAllowedValues($allowedTypes);
        }
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
     * @param FormTypeExtensionInterface[] $extensions An array of FormTypeExtensionInterface
     *
     * @throws Exception\UnexpectedTypeException if any extension does not implement FormTypeExtensionInterface
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function setExtensions(array $extensions)
    {
        trigger_error('setExtensions() is deprecated since version 2.1 and will be removed in 2.3.', E_USER_DEPRECATED);

        $this->extensions = $extensions;
    }

    /**
     * Returns the extensions associated with this type.
     *
     * @return FormTypeExtensionInterface[] An array of FormTypeExtensionInterface
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link ResolvedFormTypeInterface::getTypeExtensions()} instead.
     */
    public function getExtensions()
    {
        trigger_error('getExtensions() is deprecated since version 2.1 and will be removed in 2.3. Use ResolvedFormTypeInterface::getTypeExtensions instead.', E_USER_DEPRECATED);

        return $this->extensions;
    }
}
