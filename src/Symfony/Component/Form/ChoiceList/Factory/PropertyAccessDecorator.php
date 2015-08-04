<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Factory;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * Adds property path support to a choice list factory.
 *
 * Pass the decorated factory to the constructor:
 *
 * ```php
 * $decorator = new PropertyAccessDecorator($factory);
 * ```
 *
 * You can now pass property paths for generating choice values, labels, view
 * indices, HTML attributes and for determining the preferred choices and the
 * choice groups:
 *
 * ```php
 * // extract values from the $value property
 * $list = $createListFromChoices($objects, 'value');
 * ```
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyAccessDecorator implements ChoiceListFactoryInterface
{
    /**
     * @var ChoiceListFactoryInterface
     */
    private $decoratedFactory;

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * Decorates the given factory.
     *
     * @param ChoiceListFactoryInterface     $decoratedFactory The decorated factory
     * @param null|PropertyAccessorInterface $propertyAccessor The used property accessor
     */
    public function __construct(ChoiceListFactoryInterface $decoratedFactory, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->decoratedFactory = $decoratedFactory;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * Returns the decorated factory.
     *
     * @return ChoiceListFactoryInterface The decorated factory
     */
    public function getDecoratedFactory()
    {
        return $this->decoratedFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @param array|\Traversable                $choices The choices
     * @param null|callable|string|PropertyPath $value   The callable or path for
     *                                                   generating the choice values
     *
     * @return ChoiceListInterface The choice list
     */
    public function createListFromChoices($choices, $value = null)
    {
        if (is_string($value)) {
            $value = new PropertyPath($value);
        }

        if ($value instanceof PropertyPath) {
            $accessor = $this->propertyAccessor;
            $value = function ($choice) use ($accessor, $value) {
                // The callable may be invoked with a non-object/array value
                // when such values are passed to
                // ChoiceListInterface::getValuesForChoices(). Handle this case
                // so that the call to getValue() doesn't break.
                if (is_object($choice) || is_array($choice)) {
                    return $accessor->getValue($choice, $value);
                }
            };
        }

        return $this->decoratedFactory->createListFromChoices($choices, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param array|\Traversable                $choices The choices
     * @param null|callable|string|PropertyPath $value   The callable or path for
     *                                                   generating the choice values
     *
     * @return ChoiceListInterface The choice list
     *
     * @deprecated Added for backwards compatibility in Symfony 2.7, to be
     *             removed in Symfony 3.0.
     */
    public function createListFromFlippedChoices($choices, $value = null)
    {
        // Property paths are not supported here, because array keys can never
        // be objects
        return $this->decoratedFactory->createListFromFlippedChoices($choices, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param ChoiceLoaderInterface             $loader The choice loader
     * @param null|callable|string|PropertyPath $value  The callable or path for
     *                                                  generating the choice values
     *
     * @return ChoiceListInterface The choice list
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null)
    {
        if (is_string($value)) {
            $value = new PropertyPath($value);
        }

        if ($value instanceof PropertyPath) {
            $accessor = $this->propertyAccessor;
            $value = function ($choice) use ($accessor, $value) {
                return $accessor->getValue($choice, $value);
            };
        }

        return $this->decoratedFactory->createListFromLoader($loader, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param ChoiceListInterface                                  $list             The choice list
     * @param null|array|callable|string|PropertyPath              $preferredChoices The preferred choices
     * @param null|callable|string|PropertyPath                    $label            The callable or path generating the choice labels
     * @param null|callable|string|PropertyPath                    $index            The callable or path generating the view indices
     * @param null|array|\Traversable|callable|string|PropertyPath $groupBy          The callable or path generating the group names
     * @param null|array|callable|string|PropertyPath              $attr             The callable or path generating the HTML attributes
     * @param null|array|callable|string|PropertyPath              $labelAttr       The callable or path generating the HTML label attributes
     *
     * @return ChoiceListView The choice list view
     */
    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, $index = null, $groupBy = null, $attr = null, $labelAttr = null)
    {
        $accessor = $this->propertyAccessor;

        if (is_string($label)) {
            $label = new PropertyPath($label);
        }

        if ($label instanceof PropertyPath) {
            $label = function ($choice) use ($accessor, $label) {
                return $accessor->getValue($choice, $label);
            };
        }

        if (is_string($preferredChoices)) {
            $preferredChoices = new PropertyPath($preferredChoices);
        }

        if ($preferredChoices instanceof PropertyPath) {
            $preferredChoices = function ($choice) use ($accessor, $preferredChoices) {
                try {
                    return $accessor->getValue($choice, $preferredChoices);
                } catch (UnexpectedTypeException $e) {
                    // Assume not preferred if not readable
                    return false;
                }
            };
        }

        if (is_string($index)) {
            $index = new PropertyPath($index);
        }

        if ($index instanceof PropertyPath) {
            $index = function ($choice) use ($accessor, $index) {
                return $accessor->getValue($choice, $index);
            };
        }

        if (is_string($groupBy)) {
            $groupBy = new PropertyPath($groupBy);
        }

        if ($groupBy instanceof PropertyPath) {
            $groupBy = function ($choice) use ($accessor, $groupBy) {
                try {
                    return $accessor->getValue($choice, $groupBy);
                } catch (UnexpectedTypeException $e) {
                    // Don't group if path is not readable
                }
            };
        }

        if (is_string($attr)) {
            $attr = new PropertyPath($attr);
        }

        if (is_string($labelAttr)) {
            $labelAttr = new PropertyPath($labelAttr);
        }

        if ($attr instanceof PropertyPath) {
            $attr = function ($choice) use ($accessor, $attr) {
                return $accessor->getValue($choice, $attr);
            };
        }

        if ($labelAttr instanceof PropertyPath) {
            $labelAttr = function ($choice) use ($accessor, $labelAttr) {
                return $accessor->getValue($choice, $labelAttr);
            };
        }

        return $this->decoratedFactory->createView($list, $preferredChoices, $label, $index, $groupBy, $attr, $labelAttr);
    }
}
