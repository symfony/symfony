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
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeZoneToStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimezoneType extends AbstractType implements ChoiceLoaderInterface
{
    /**
     * Timezone loaded choice list.
     *
     * The choices are generated from the ICU function \DateTimeZone::listIdentifiers().
     *
     * @var ArrayChoiceList
     *
     * @deprecated since version 3.4, to be removed in 4.0
     */
    private $choiceList;

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ('datetimezone' === $options['input']) {
            $builder->addModelTransformer(new DateTimeZoneToStringTransformer($options['multiple']));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choice_loader' => function (Options $options) {
                if ($options['choices']) {
                    @trigger_error(sprintf('Using the "choices" option in %s has been deprecated since Symfony 3.3 and will be ignored in 4.0. Override the "choice_loader" option instead or set it to null.', __CLASS__), \E_USER_DEPRECATED);

                    return null;
                }

                $regions = $options['regions'];

                return new CallbackChoiceLoader(function () use ($regions) {
                    return self::getTimezones($regions);
                });
            },
            'choice_translation_domain' => false,
            'input' => 'string',
            'regions' => \DateTimeZone::ALL,
        ]);

        $resolver->setAllowedValues('input', ['string', 'datetimezone']);

        $resolver->setAllowedTypes('regions', 'int');
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'timezone';
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since version 3.4, to be removed in 4.0
     */
    public function loadChoiceList($value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0.', __METHOD__), \E_USER_DEPRECATED);

        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        return $this->choiceList = new ArrayChoiceList(self::getTimezones(\DateTimeZone::ALL), $value);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since version 3.4, to be removed in 4.0
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0.', __METHOD__), \E_USER_DEPRECATED);

        // Optimize
        $values = array_filter($values);
        if (empty($values)) {
            return [];
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
     * @deprecated since version 3.4, to be removed in 4.0
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0.', __METHOD__), \E_USER_DEPRECATED);

        // Optimize
        $choices = array_filter($choices);
        if (empty($choices)) {
            return [];
        }

        // If no callable is set, choices are the same as values
        if (null === $value) {
            return $choices;
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }

    /**
     * Returns a normalized array of timezone choices.
     *
     * @param int $regions
     *
     * @return array The timezone choices
     */
    private static function getTimezones($regions)
    {
        $timezones = [];

        foreach (\DateTimeZone::listIdentifiers($regions) as $timezone) {
            $parts = explode('/', $timezone);

            if (\count($parts) > 2) {
                $region = $parts[0];
                $name = $parts[1].' - '.$parts[2];
            } elseif (\count($parts) > 1) {
                $region = $parts[0];
                $name = $parts[1];
            } else {
                $region = 'Other';
                $name = $parts[0];
            }

            $timezones[$region][str_replace('_', ' ', $name)] = $timezone;
        }

        return 1 === \count($timezones) ? reset($timezones) : $timezones;
    }
}
