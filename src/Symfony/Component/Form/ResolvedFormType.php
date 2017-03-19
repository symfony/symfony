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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A wrapper for a form type and its extensions.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedFormType implements ResolvedFormTypeInterface
{
    /**
     * @var FormTypeInterface
     */
    private $innerType;

    /**
     * @var FormTypeExtensionInterface[]
     */
    private $typeExtensions;

    /**
     * @var ResolvedFormTypeInterface|null
     */
    private $parent;

    /**
     * @var OptionsResolver
     */
    private $optionsResolver;

    public function __construct(FormTypeInterface $innerType, array $typeExtensions = array(), ResolvedFormTypeInterface $parent = null)
    {
        foreach ($typeExtensions as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormTypeExtensionInterface');
            }
        }

        $this->innerType = $innerType;
        $this->typeExtensions = $typeExtensions;
        $this->parent = $parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return $this->innerType->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getInnerType()
    {
        return $this->innerType;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeExtensions()
    {
        return $this->typeExtensions;
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder(FormFactoryInterface $factory, $name, array $options = array())
    {
        $options = $this->getOptionsResolver()->resolve($options);

        // Should be decoupled from the specific option at some point
        $dataClass = isset($options['data_class']) ? $options['data_class'] : null;

        $builder = $this->newBuilder($name, $dataClass, $factory, $options);
        $builder->setType($this);

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function createView(FormInterface $form, FormView $parent = null)
    {
        return $this->newView($parent);
    }

    /**
     * Configures a form builder for the type hierarchy.
     *
     * @param FormBuilderInterface $builder The builder to configure
     * @param array                $options The options used for the configuration
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (null !== $this->parent) {
            $this->parent->buildForm($builder, $options);
        }

        $this->innerType->buildForm($builder, $options);

        foreach ($this->typeExtensions as $extension) {
            $extension->buildForm($builder, $options);
        }
    }

    /**
     * Configures a form view for the type hierarchy.
     *
     * This method is called before the children of the view are built.
     *
     * @param FormView      $view    The form view to configure
     * @param FormInterface $form    The form corresponding to the view
     * @param array         $options The options used for the configuration
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (null !== $this->parent) {
            $this->parent->buildView($view, $form, $options);
        }

        $this->innerType->buildView($view, $form, $options);

        foreach ($this->typeExtensions as $extension) {
            $extension->buildView($view, $form, $options);
        }
    }

    /**
     * Finishes a form view for the type hierarchy.
     *
     * This method is called after the children of the view have been built.
     *
     * @param FormView      $view    The form view to configure
     * @param FormInterface $form    The form corresponding to the view
     * @param array         $options The options used for the configuration
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if (null !== $this->parent) {
            $this->parent->finishView($view, $form, $options);
        }

        $this->innerType->finishView($view, $form, $options);

        foreach ($this->typeExtensions as $extension) {
            /* @var FormTypeExtensionInterface $extension */
            $extension->finishView($view, $form, $options);
        }
    }

    /**
     * Returns the configured options resolver used for this type.
     *
     * @return \Symfony\Component\OptionsResolver\OptionsResolver The options resolver
     */
    public function getOptionsResolver()
    {
        if (null === $this->optionsResolver) {
            if (null !== $this->parent) {
                $this->optionsResolver = clone $this->parent->getOptionsResolver();
            } else {
                $this->optionsResolver = new OptionsResolver();
            }

            $this->innerType->configureOptions($this->optionsResolver);

            foreach ($this->typeExtensions as $extension) {
                $extension->configureOptions($this->optionsResolver);
            }
        }

        return $this->optionsResolver;
    }

    /**
     * Creates a new builder instance.
     *
     * Override this method if you want to customize the builder class.
     *
     * @param string               $name      The name of the builder
     * @param string               $dataClass The data class
     * @param FormFactoryInterface $factory   The current form factory
     * @param array                $options   The builder options
     *
     * @return FormBuilderInterface The new builder instance
     */
    protected function newBuilder($name, $dataClass, FormFactoryInterface $factory, array $options)
    {
        if ($this->innerType instanceof ButtonTypeInterface) {
            return new ButtonBuilder($name, $options);
        }

        if ($this->innerType instanceof SubmitButtonTypeInterface) {
            return new SubmitButtonBuilder($name, $options);
        }

        return new FormBuilder($name, $dataClass, new EventDispatcher(), $factory, $options);
    }

    /**
     * Creates a new view instance.
     *
     * Override this method if you want to customize the view class.
     *
     * @param FormView|null $parent The parent view, if available
     *
     * @return FormView A new view instance
     */
    protected function newView(FormView $parent = null)
    {
        return new FormView($parent);
    }
}
