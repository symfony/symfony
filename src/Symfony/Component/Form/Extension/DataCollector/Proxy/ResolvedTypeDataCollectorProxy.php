<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector\Proxy;

use Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormTypeInterface;

/**
 * Proxy that invokes a data collector when creating a form and its view.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedTypeDataCollectorProxy implements ResolvedFormTypeInterface
{
    /**
     * @var ResolvedFormTypeInterface
     */
    private $proxiedType;

    /**
     * @var FormDataCollectorInterface
     */
    private $dataCollector;

    public function __construct(ResolvedFormTypeInterface $proxiedType, FormDataCollectorInterface $dataCollector)
    {
        $this->proxiedType = $proxiedType;
        $this->dataCollector = $dataCollector;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->proxiedType->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return method_exists($this->proxiedType, 'getBlockPrefix') ? $this->proxiedType->getBlockPrefix() : $this->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->proxiedType->getParent();
    }

    /**
     * {@inheritdoc}
     */
    public function getInnerType()
    {
        return $this->proxiedType->getInnerType();
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeExtensions()
    {
        return $this->proxiedType->getTypeExtensions();
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder(FormFactoryInterface $factory, $name, array $options = array())
    {
        $builder = $this->proxiedType->createBuilder($factory, $name, $options);

        $builder->setAttribute('data_collector/passed_options', $options);
        $builder->setType($this);

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function createView(FormInterface $form, FormView $parent = null)
    {
        return $this->proxiedType->createView($form, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->proxiedType->buildForm($builder, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $this->proxiedType->buildView($view, $form, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->proxiedType->finishView($view, $form, $options);

        // Remember which view belongs to which form instance, so that we can
        // get the collected data for a view when its form instance is not
        // available (e.g. CSRF token)
        $this->dataCollector->associateFormWithView($form, $view);

        // Since the CSRF token is only present in the FormView tree, we also
        // need to check the FormView tree instead of calling isRoot() on the
        // FormInterface tree
        if (null === $view->parent) {
            $this->dataCollector->collectViewVariables($view);

            // Re-assemble data, in case FormView instances were added, for
            // which no FormInterface instances were present (e.g. CSRF token).
            // Since finishView() is called after finishing the views of all
            // children, we can safely assume that information has been
            // collected about the complete form tree.
            $this->dataCollector->buildFinalFormTree($form, $view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsResolver()
    {
        return $this->proxiedType->getOptionsResolver();
    }
}
