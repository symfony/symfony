<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList;

use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceAttr;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceFieldName;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceFilter;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceLabel;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceLoader;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceTranslationParameters;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceValue;
use Symfony\Component\Form\ChoiceList\Factory\Cache\GroupBy;
use Symfony\Component\Form\ChoiceList\Factory\Cache\PreferredChoice;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeInterface;

/**
 * A set of convenient static methods to create cacheable choice list options.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class ChoiceList
{
    /**
     * Creates a cacheable loader from any callable providing iterable choices.
     *
     * @param callable $choices A callable that must return iterable choices or grouped choices
     * @param mixed    $vary    Dynamic data used to compute a unique hash when caching the loader
     */
    public static function lazy(FormTypeInterface|FormTypeExtensionInterface $formType, callable $choices, mixed $vary = null): ChoiceLoader
    {
        return self::loader($formType, new CallbackChoiceLoader($choices), $vary);
    }

    /**
     * Decorates a loader to make it cacheable.
     *
     * @param ChoiceLoaderInterface $loader A loader responsible for creating loading choices or grouped choices
     * @param mixed                 $vary   Dynamic data used to compute a unique hash when caching the loader
     */
    public static function loader(FormTypeInterface|FormTypeExtensionInterface $formType, ChoiceLoaderInterface $loader, mixed $vary = null): ChoiceLoader
    {
        return new ChoiceLoader($formType, $loader, $vary);
    }

    /**
     * Decorates a "choice_value" callback to make it cacheable.
     *
     * @param callable|array $value Any pseudo callable to create a unique string value from a choice
     * @param mixed          $vary  Dynamic data used to compute a unique hash when caching the callback
     */
    public static function value(FormTypeInterface|FormTypeExtensionInterface $formType, callable|array $value, mixed $vary = null): ChoiceValue
    {
        return new ChoiceValue($formType, $value, $vary);
    }

    /**
     * @param callable|array $filter Any pseudo callable to filter a choice list
     * @param mixed          $vary   Dynamic data used to compute a unique hash when caching the callback
     */
    public static function filter(FormTypeInterface|FormTypeExtensionInterface $formType, callable|array $filter, mixed $vary = null): ChoiceFilter
    {
        return new ChoiceFilter($formType, $filter, $vary);
    }

    /**
     * Decorates a "choice_label" option to make it cacheable.
     *
     * @param callable|false $label Any pseudo callable to create a label from a choice or false to discard it
     * @param mixed          $vary  Dynamic data used to compute a unique hash when caching the option
     */
    public static function label(FormTypeInterface|FormTypeExtensionInterface $formType, callable|false $label, mixed $vary = null): ChoiceLabel
    {
        return new ChoiceLabel($formType, $label, $vary);
    }

    /**
     * Decorates a "choice_name" callback to make it cacheable.
     *
     * @param callable|array $fieldName Any pseudo callable to create a field name from a choice
     * @param mixed          $vary      Dynamic data used to compute a unique hash when caching the callback
     */
    public static function fieldName(FormTypeInterface|FormTypeExtensionInterface $formType, callable|array $fieldName, mixed $vary = null): ChoiceFieldName
    {
        return new ChoiceFieldName($formType, $fieldName, $vary);
    }

    /**
     * Decorates a "choice_attr" option to make it cacheable.
     *
     * @param callable|array $attr Any pseudo callable or array to create html attributes from a choice
     * @param mixed          $vary Dynamic data used to compute a unique hash when caching the option
     */
    public static function attr(FormTypeInterface|FormTypeExtensionInterface $formType, callable|array $attr, mixed $vary = null): ChoiceAttr
    {
        return new ChoiceAttr($formType, $attr, $vary);
    }

    /**
     * Decorates a "choice_translation_parameters" option to make it cacheable.
     *
     * @param callable|array $translationParameters Any pseudo callable or array to create translation parameters from a choice
     * @param mixed          $vary                  Dynamic data used to compute a unique hash when caching the option
     */
    public static function translationParameters(FormTypeInterface|FormTypeExtensionInterface $formType, callable|array $translationParameters, mixed $vary = null): ChoiceTranslationParameters
    {
        return new ChoiceTranslationParameters($formType, $translationParameters, $vary);
    }

    /**
     * Decorates a "group_by" callback to make it cacheable.
     *
     * @param callable|array $groupBy Any pseudo callable to return a group name from a choice
     * @param mixed          $vary    Dynamic data used to compute a unique hash when caching the callback
     */
    public static function groupBy(FormTypeInterface|FormTypeExtensionInterface $formType, callable|array $groupBy, mixed $vary = null): GroupBy
    {
        return new GroupBy($formType, $groupBy, $vary);
    }

    /**
     * Decorates a "preferred_choices" option to make it cacheable.
     *
     * @param callable|array $preferred Any pseudo callable or array to return a group name from a choice
     * @param mixed          $vary      Dynamic data used to compute a unique hash when caching the option
     */
    public static function preferred(FormTypeInterface|FormTypeExtensionInterface $formType, callable|array $preferred, mixed $vary = null): PreferredChoice
    {
        return new PreferredChoice($formType, $preferred, $vary);
    }

    /**
     * Should not be instantiated.
     */
    private function __construct()
    {
    }
}
