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

    /**
     * Returns the default options for this type.
     *
     * @param array $options
     *
     * @return array The default options
     */
    public function getDefaultOptions(array $options)
    {
        return array();
    }

    /**
     * Returns the allowed option values for each option (if any).
     *
     * @param array $options
     *
     * @return array The allowed option values
     */
    public function getAllowedOptionValues(array $options)
    {
        return array();
    }

    /**
     * Returns the name of the parent type.
     *
     * @param array $options
     *
     * @return string The name of the parent type
     */
    public function getParent(array $options)
    {
        return 'form';
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        preg_match('/\\\\(\w+?)(Form)?(Type)?$/i', get_class($this), $matches);

        return strtolower($matches[1]);
    }

    /**
     * Adds extensions for this type.
     *
     * @param array $extensions An array of FormTypeExtensionInterface
     *
     * @throws UnexpectedTypeException if any extension does not implement FormTypeExtensionInterface
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
     * Returns the extensions associated with this type.
     *
     * @return array An array of FormTypeExtensionInterface
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
}
