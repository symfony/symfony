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

use Symfony\Component\Form\Exception\InvalidConfigurationException;
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
     * @var array
     */
    private $typeExtensions;

    /**
     * @var ResolvedFormTypeInterface
     */
    private $parent;

    /**
     * @var OptionsResolver
     */
    private $optionsResolver;

    public function __construct(FormTypeInterface $innerType, array $typeExtensions = array(), ResolvedFormTypeInterface $parent = null)
    {
        if (!preg_match('/^[a-z0-9_]*$/i', $innerType->getName())) {
            throw new Exception(sprintf(
                'The "%s" form type name ("%s") is not valid. Names must only contain letters, numbers, and "_".',
                get_class($innerType),
                $innerType->getName()
            ));
        }

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
    public function getName()
    {
        return $this->innerType->getName();
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
        // BC
        if ($this->innerType instanceof AbstractType) {
            return $this->innerType->getExtensions();
        }

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

        $this->buildForm($builder, $options);

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function createView(FormInterface $form, FormView $parent = null)
    {
        $options = $form->getConfig()->getOptions();

        $view = $this->newView($parent);
        $this->buildView($view, $form, $options);

        foreach ($this->getOrderedFormChilds($form) as $name) {
            $view->children[$name] = $form[$name]->createView($view);
        }

        $this->finishView($view, $form, $options);

        return $view;
    }

    /**
     * Configures a form builder for the type hierarchy.
     *
     * This method is protected in order to allow implementing classes
     * to change or call it in re-implementations of {@link createBuilder()}.
     *
     * @param FormBuilderInterface $builder The builder to configure.
     * @param array                $options The options used for the configuration.
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (null !== $this->parent) {
            $this->parent->buildForm($builder, $options);
        }

        $this->innerType->buildForm($builder, $options);

        foreach ($this->typeExtensions as $extension) {
            /* @var FormTypeExtensionInterface $extension */
            $extension->buildForm($builder, $options);
        }
    }

    /**
     * Configures a form view for the type hierarchy.
     *
     * This method is protected in order to allow implementing classes
     * to change or call it in re-implementations of {@link createView()}.
     *
     * It is called before the children of the view are built.
     *
     * @param FormView      $view    The form view to configure.
     * @param FormInterface $form    The form corresponding to the view.
     * @param array         $options The options used for the configuration.
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (null !== $this->parent) {
            $this->parent->buildView($view, $form, $options);
        }

        $this->innerType->buildView($view, $form, $options);

        foreach ($this->typeExtensions as $extension) {
            /* @var FormTypeExtensionInterface $extension */
            $extension->buildView($view, $form, $options);
        }
    }

    /**
     * Finishes a form view for the type hierarchy.
     *
     * This method is protected in order to allow implementing classes
     * to change or call it in re-implementations of {@link createView()}.
     *
     * It is called after the children of the view have been built.
     *
     * @param FormView      $view    The form view to configure.
     * @param FormInterface $form    The form corresponding to the view.
     * @param array         $options The options used for the configuration.
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
     * This method is protected in order to allow implementing classes
     * to change or call it in re-implementations of {@link createBuilder()}.
     *
     * @return \Symfony\Component\OptionsResolver\OptionsResolverInterface The options resolver.
     */
    public function getOptionsResolver()
    {
        if (null === $this->optionsResolver) {
            if (null !== $this->parent) {
                $this->optionsResolver = clone $this->parent->getOptionsResolver();
            } else {
                $this->optionsResolver = new OptionsResolver();
            }

            $this->innerType->setDefaultOptions($this->optionsResolver);

            foreach ($this->typeExtensions as $extension) {
                /* @var FormTypeExtensionInterface $extension */
                $extension->setDefaultOptions($this->optionsResolver);
            }
        }

        return $this->optionsResolver;
    }

    /**
     * Creates a new builder instance.
     *
     * Override this method if you want to customize the builder class.
     *
     * @param string               $name      The name of the builder.
     * @param string               $dataClass The data class.
     * @param FormFactoryInterface $factory   The current form factory.
     * @param array                $options   The builder options.
     *
     * @return FormBuilderInterface The new builder instance.
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
     * @param FormView|null $parent The parent view, if available.
     *
     * @return FormView A new view instance.
     */
    protected function newView(FormView $parent = null)
    {
        return new FormView($parent);
    }

    /**
     * Returns the ordered childs form name of a form.
     *
     * @param \Symfony\Component\Form\FormInterface $form The form.
     *
     * @return array The ordered childs form name.
     */
    protected function getOrderedFormChilds(FormInterface $form)
    {
        $cachedPositions = array();

        $weights = array();
        $afterGapWeights = array();
        $firstFormWeight = 0;
        $lastFormWeight = 0;

        $differredBefores = array();
        $differredAfters = array();

        /* @var FormInterface $child */
        foreach ($form as $child) {
            $position = $child->getConfig()->getPosition();

            if (is_string($position)) {
                if ($position === 'first') {
                    if (isset($differredBefores[$child->getName()])) {
                        foreach ($differredBefores[$child->getName()] as $differredBefore) {
                            $weights = $this->incrementWeights($weights, $firstFormWeight);
                            $weights[$differredBefore] = $firstFormWeight++;
                            $lastFormWeight++;
                        }
                    }

                    $weights = $this->incrementWeights($weights, $firstFormWeight);
                    $weights[$child->getName()] = $firstFormWeight++;
                    $lastFormWeight++;

                    if (isset($differredAfters[$child->getName()])) {
                        foreach ($differredAfters[$child->getName()] as $differredAfter) {
                            $weights = $this->incrementWeights($weights, $firstFormWeight);
                            $weights[$differredAfter] = $firstFormWeight++;
                            $lastFormWeight++;
                        }
                    }
                } else {
                    if (isset($differredBefores[$child->getName()])) {
                        foreach ($differredBefores[$child->getName()] as $differredBefore) {
                            $weights[$differredBefore] = empty($weights) ? 0 : max($weights) + 1;
                            $lastFormWeight++;
                        }
                    }

                    $weights[$child->getName()] = empty($weights) ? 0 : max($weights) + 1;

                    if (isset($differredAfters[$child->getName()])) {
                        foreach ($differredAfters[$child->getName()] as $differredAfter) {
                            $weights[$differredAfter] = empty($weights) ? 0 : max($weights) + 1;
                            $lastFormWeight++;
                        }
                    }
                }

                continue;
            } elseif (is_array($position)) {
                if (isset($position['before'])) {
                    $cachedPositions[$child->getName()]['before'] = $position['before'];

                    if (!isset($cachedPositions[$position['before']]['after'])) {
                        $cachedPositions[$position['before']]['after'] = $child->getName();
                    }
                } elseif (!isset($cachedPositions[$child->getName()]['before'])) {
                    $cachedPositions[$child->getName()]['before'] = null;
                }

                if (isset($position['after'])) {
                    $cachedPositions[$child->getName()]['after'] = $position['after'];

                    if (!isset($cachedPositions[$position['after']]['before'])) {
                        $cachedPositions[$position['after']]['before'] = $child->getName();
                    }
                } elseif (!isset($cachedPositions[$child->getName()]['after'])) {
                    $cachedPositions[$child->getName()]['after'] = null;
                }

                $before = $cachedPositions[$child->getName()]['before'];
                if ($before !== null) {
                    $this->detectCircularBeforeAndAfterReferences($cachedPositions, $before, $child->getName());

                    if (isset($weights[$before])) {
                        $beforeOrder = $weights[$before];
                        $weights = $this->incrementWeights($weights, $weights[$before]);
                        $weights[$child->getName()] = $beforeOrder;
                        $lastFormWeight++;
                    } else {
                        if (isset($differredBefores[$before])) {
                            $differredBefores[$before][] = $child->getName();
                        } else {
                            $differredBefores[$before] = array($child->getName());
                        }
                    }

                    continue;
                }

                $after = $cachedPositions[$child->getName()]['after'];
                if ($after !== null) {
                    if (isset($weights[$after])) {
                        if (!isset($afterGapWeights[$after])) {
                            $afterGapWeights[$after] = 0;
                        }

                        $newOrder = $weights[$after] + $afterGapWeights[$after] + 1;
                        $weights = $this->incrementWeights($weights, $newOrder);
                        $weights[$child->getName()] = $newOrder;
                        $lastFormWeight++;

                        $afterGapWeights[$after]++;
                    } else {
                        if (isset($differredAfters[$after])) {
                            $differredAfters[$after][] = $child->getName();
                        } else {
                            $differredAfters[$after] = array($child->getName());
                        }
                    }

                    continue;
                }
            }

            if (isset($differredBefores[$child->getName()])) {
                foreach ($differredBefores[$child->getName()] as $differredBefore) {
                    $weights = $this->incrementWeights($weights, $lastFormWeight);
                    $weights[$differredBefore] = $lastFormWeight++;
                }
            }

            $weights = $this->incrementWeights($weights, $lastFormWeight);
            $weights[$child->getName()] = $lastFormWeight++;

            if (isset($differredAfters[$child->getName()])) {
                foreach ($differredAfters[$child->getName()] as $differredAfter) {
                    $weights = $this->incrementWeights($weights, $lastFormWeight);
                    $weights[$differredAfter] = $lastFormWeight++;
                }
            }
        }

        asort($weights, SORT_NUMERIC);

        return array_keys($weights);
    }

    /**
     * Increments all fields weights greater than start.
     *
     * @param array   $weights The form weights.
     * @param integer $start   The start.
     *
     * @return array The form weights incremented.
     */
    private function incrementWeights(array $weights, $start)
    {
        if (!empty($weights) && (max($weights) >= $start)) {
            foreach ($weights as &$weight) {
                if ($weight >= $start) {
                    $weight++;
                }
            }
        }

        return $weights;
    }

    /**
     * Detetects a circle before/after references.
     *
     * @param array  $positions The cached positions.
     * @param string $item      The checked item.
     * @param string $name      The original item name.
     *
     * @throws InvalidConfigurationException If there is a circular before/after references.
     */
    private function detectCircularBeforeAndAfterReferences(array $positions, $item, $name)
    {
        if (isset($positions[$item]['before'])) {
            if ($positions[$item]['before'] === $name) {
                throw new InvalidConfigurationException(sprintf(
                    'The form ordering cannot be resolved due to conflict in after/before options. '.
                    'The field "%s" can not have "%s" as before field if the field "%s" have "%s" as before field.',
                    $name,
                    $item,
                    $item,
                    $name
                ));
            }

            $this->detectCircularBeforeAndAfterReferences($positions, $positions[$item]['before'], $name);
        }
    }
}
