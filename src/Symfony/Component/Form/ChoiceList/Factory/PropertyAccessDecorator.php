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
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * Adds property path support to a choice list factory.
 *
 * Pass the decorated factory to the constructor:
 *
 *     $decorator = new PropertyAccessDecorator($factory);
 *
 * You can now pass property paths for generating choice values, labels, view
 * indices, HTML attributes and for determining the preferred choices and the
 * choice groups:
 *
 *     // extract values from the $value property
 *     $list = $createListFromChoices($objects, 'value');
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyAccessDecorator implements ChoiceListFactoryInterface
{
    private $decoratedFactory;
    private $propertyAccessor;

    public function __construct(ChoiceListFactoryInterface $decoratedFactory, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->decoratedFactory = $decoratedFactory;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * Returns the decorated factory.
     *
     * @return ChoiceListFactoryInterface
     */
    public function getDecoratedFactory()
    {
        return $this->decoratedFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $value
     * @param mixed $filter
     *
     * @return ChoiceListInterface
     */
    public function createListFromChoices(iterable $choices, $value = null/*, $filter = null*/)
    {
        $filter = \func_num_args() > 2 ? func_get_arg(2) : null;

        if (\is_string($value)) {
            $value = new PropertyPath($value);
        }

        if ($value instanceof PropertyPathInterface) {
            $accessor = $this->propertyAccessor;
            $value = function ($choice) use ($accessor, $value) {
                // The callable may be invoked with a non-object/array value
                // when such values are passed to
                // ChoiceListInterface::getValuesForChoices(). Handle this case
                // so that the call to getValue() doesn't break.
                return \is_object($choice) || \is_array($choice) ? $accessor->getValue($choice, $value) : null;
            };
        }

        if (\is_string($filter)) {
            $filter = new PropertyPath($filter);
        }

        if ($filter instanceof PropertyPath) {
            $accessor = $this->propertyAccessor;
            $filter = static function ($choice) use ($accessor, $filter) {
                return (\is_object($choice) || \is_array($choice)) && $accessor->getValue($choice, $filter);
            };
        }

        return $this->decoratedFactory->createListFromChoices($choices, $value, $filter);
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $value
     * @param mixed $filter
     *
     * @return ChoiceListInterface
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null/*, $filter = null*/)
    {
        $filter = \func_num_args() > 2 ? func_get_arg(2) : null;

        if (\is_string($value)) {
            $value = new PropertyPath($value);
        }

        if ($value instanceof PropertyPathInterface) {
            $accessor = $this->propertyAccessor;
            $value = function ($choice) use ($accessor, $value) {
                // The callable may be invoked with a non-object/array value
                // when such values are passed to
                // ChoiceListInterface::getValuesForChoices(). Handle this case
                // so that the call to getValue() doesn't break.
                return \is_object($choice) || \is_array($choice) ? $accessor->getValue($choice, $value) : null;
            };
        }

        if (\is_string($filter)) {
            $filter = new PropertyPath($filter);
        }

        if ($filter instanceof PropertyPath) {
            $accessor = $this->propertyAccessor;
            $filter = static function ($choice) use ($accessor, $filter) {
                return (\is_object($choice) || \is_array($choice)) && $accessor->getValue($choice, $filter);
            };
        }

        return $this->decoratedFactory->createListFromLoader($loader, $value, $filter);
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $preferredChoices
     * @param mixed $label
     * @param mixed $index
     * @param mixed $groupBy
     * @param mixed $attr
     * @param mixed $labelTranslationParameters
     *
     * @return ChoiceListView
     */
    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, $index = null, $groupBy = null, $attr = null/*, $labelTranslationParameters = []*/)
    {
        $labelTranslationParameters = \func_num_args() > 6 ? func_get_arg(6) : [];
        $accessor = $this->propertyAccessor;

        if (\is_string($label)) {
            $label = new PropertyPath($label);
        }

        if ($label instanceof PropertyPathInterface) {
            $label = function ($choice) use ($accessor, $label) {
                return $accessor->getValue($choice, $label);
            };
        }

        if (\is_string($preferredChoices)) {
            $preferredChoices = new PropertyPath($preferredChoices);
        }

        if ($preferredChoices instanceof PropertyPathInterface) {
            $preferredChoices = function ($choice) use ($accessor, $preferredChoices) {
                try {
                    return $accessor->getValue($choice, $preferredChoices);
                } catch (UnexpectedTypeException $e) {
                    // Assume not preferred if not readable
                    return false;
                }
            };
        }

        if (\is_string($index)) {
            $index = new PropertyPath($index);
        }

        if ($index instanceof PropertyPathInterface) {
            $index = function ($choice) use ($accessor, $index) {
                return $accessor->getValue($choice, $index);
            };
        }

        if (\is_string($groupBy)) {
            $groupBy = new PropertyPath($groupBy);
        }

        if ($groupBy instanceof PropertyPathInterface) {
            $groupBy = function ($choice) use ($accessor, $groupBy) {
                try {
                    return $accessor->getValue($choice, $groupBy);
                } catch (UnexpectedTypeException $e) {
                    // Don't group if path is not readable
                    return null;
                }
            };
        }

        if (\is_string($attr)) {
            $attr = new PropertyPath($attr);
        }

        if ($attr instanceof PropertyPathInterface) {
            $attr = function ($choice) use ($accessor, $attr) {
                return $accessor->getValue($choice, $attr);
            };
        }

        if (\is_string($labelTranslationParameters)) {
            $labelTranslationParameters = new PropertyPath($labelTranslationParameters);
        }

        if ($labelTranslationParameters instanceof PropertyPath) {
            $labelTranslationParameters = static function ($choice) use ($accessor, $labelTranslationParameters) {
                return $accessor->getValue($choice, $labelTranslationParameters);
            };
        }

        return $this->decoratedFactory->createView(
            $list,
            $preferredChoices,
            $label,
            $index,
            $groupBy,
            $attr,
            $labelTranslationParameters
        );
    }
}
