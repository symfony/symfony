<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\ArrayInclusionFilter;
use Symfony\Component\Intl\Intl;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CountryType extends AbstractType implements ChoiceLoaderInterface
{
    /**
     * Country loaded choice list.
     *
     * The choices are lazy loaded and generated from the Intl component.
     *
     * {@link \Symfony\Component\Intl\Intl::getRegionBundle()}.
     *
     * @var ArrayChoiceList
     *
     * @deprecated since Symfony 4.1
     */
    private $choiceList;

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'choice_loader' => function (Options $options) {
                $choiceTranslationLocale = $options['choice_translation_locale'];
                $supportedCountries = $options['supported_countries'];

                return new IntlCallbackChoiceLoader(function () use ($choiceTranslationLocale, $supportedCountries) {
                    $countries = Intl::getRegionBundle()->getCountryNames($choiceTranslationLocale);

                    if (null !== $supportedCountries) {
                        $supportedCountries = array_map('strtoupper', $supportedCountries);
                        $countries = array_filter($countries, new ArrayInclusionFilter($supportedCountries), ARRAY_FILTER_USE_KEY);
                    }

                    return array_flip($countries);
                });
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'supported_countries' => null,
        ));

        $resolver->setAllowedTypes('choice_translation_locale', array('null', 'string'));
        $resolver->setAllowedTypes('supported_countries', array('null', 'string[]'));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return __NAMESPACE__.'\ChoiceType';
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'country';
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 4.1
     */
    public function loadChoiceList($value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, use the "choice_loader" option instead.', __METHOD__), E_USER_DEPRECATED);

        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        return $this->choiceList = new ArrayChoiceList(array_flip(Intl::getRegionBundle()->getCountryNames()), $value);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 4.1
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, use the "choice_loader" option instead.', __METHOD__), E_USER_DEPRECATED);

        // Optimize
        $values = array_filter($values);
        if (empty($values)) {
            return array();
        }

        // If no callable is set, values are the same as choices
        if (null === $value) {
            return $values;
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 4.1
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, use the "choice_loader" option instead.', __METHOD__), E_USER_DEPRECATED);

        // Optimize
        $choices = array_filter($choices);
        if (empty($choices)) {
            return array();
        }

        // If no callable is set, choices are the same as values
        if (null === $value) {
            return $choices;
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
