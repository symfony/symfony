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
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param callable                                     $choices  A callable that must return iterable choices or grouped choices
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the loader
     */
    public static function lazy($formType, callable $choices, $vary = null): ChoiceLoader
    {
        return self::loader($formType, new CallbackChoiceLoader($choices), $vary);
    }

    /**
     * Decorates a loader to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param ChoiceLoaderInterface                        $loader   A loader responsible for creating loading choices or grouped choices
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the loader
     */
    public static function loader($formType, ChoiceLoaderInterface $loader, $vary = null): ChoiceLoader
    {
        return new ChoiceLoader($formType, $loader, $vary);
    }

    /**
     * Decorates a "choice_value" callback to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param callable                                     $value    Any pseudo callable to create a unique string value from a choice
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the callback
     */
    public static function value($formType, $value, $vary = null): ChoiceValue
    {
        return new ChoiceValue($formType, $value, $vary);
    }

    /**
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param callable                                     $filter   Any pseudo callable to filter a choice list
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the callback
     */
    public static function filter($formType, $filter, $vary = null): ChoiceFilter
    {
        return new ChoiceFilter($formType, $filter, $vary);
    }

    /**
     * Decorates a "choice_label" option to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param callable|false                               $label    Any pseudo callable to create a label from a choice or false to discard it
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the option
     */
    public static function label($formType, $label, $vary = null): ChoiceLabel
    {
        return new ChoiceLabel($formType, $label, $vary);
    }

    /**
     * Decorates a "choice_name" callback to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType  A form type or type extension configuring a cacheable choice list
     * @param callable                                     $fieldName Any pseudo callable to create a field name from a choice
     * @param mixed|null                                   $vary      Dynamic data used to compute a unique hash when caching the callback
     */
    public static function fieldName($formType, $fieldName, $vary = null): ChoiceFieldName
    {
        return new ChoiceFieldName($formType, $fieldName, $vary);
    }

    /**
     * Decorates a "choice_attr" option to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param callable|array                               $attr     Any pseudo callable or array to create html attributes from a choice
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the option
     */
    public static function attr($formType, $attr, $vary = null): ChoiceAttr
    {
        return new ChoiceAttr($formType, $attr, $vary);
    }

    /**
     * Decorates a "choice_translation_parameters" option to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType              A form type or type extension configuring a cacheable choice list
     * @param callable|array                               $translationParameters Any pseudo callable or array to create translation parameters from a choice
     * @param mixed|null                                   $vary                  Dynamic data used to compute a unique hash when caching the option
     */
    public static function translationParameters($formType, $translationParameters, $vary = null): ChoiceTranslationParameters
    {
        return new ChoiceTranslationParameters($formType, $translationParameters, $vary);
    }

    /**
     * Decorates a "group_by" callback to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType A form type or type extension configuring a cacheable choice list
     * @param callable                                     $groupBy  Any pseudo callable to return a group name from a choice
     * @param mixed|null                                   $vary     Dynamic data used to compute a unique hash when caching the callback
     */
    public static function groupBy($formType, $groupBy, $vary = null): GroupBy
    {
        return new GroupBy($formType, $groupBy, $vary);
    }

    /**
     * Decorates a "preferred_choices" option to make it cacheable.
     *
     * @param FormTypeInterface|FormTypeExtensionInterface $formType  A form type or type extension configuring a cacheable choice list
     * @param callable|array                               $preferred Any pseudo callable or array to return a group name from a choice
     * @param mixed|null                                   $vary      Dynamic data used to compute a unique hash when caching the option
     */
    public static function preferred($formType, $preferred, $vary = null): PreferredChoice
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
