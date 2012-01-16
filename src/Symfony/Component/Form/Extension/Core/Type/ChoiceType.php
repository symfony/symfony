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
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Extension\Core\ChoiceList\Loader\ChoiceListLoaderInterface;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\Extension\Core\EventListener\FixRadioInputListener;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToBooleanArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToBooleanArrayTransformer;

class ChoiceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        if ($options['choice_list'] && !$options['choice_list'] instanceof ChoiceListInterface) {
            throw new FormException('The "choice_list" must be an instance of "Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface".');
        }

        if (!$options['choice_list'] && !$options['choices']) {
            throw new FormException('Either the option "choices" or "choice_list" must be set.');
        }

        if (!$options['choice_list']) {
            $options['choice_list'] = new SimpleChoiceList(
                $options['choices'],
                $options['preferred_choices'],
                $options['value_strategy'],
                $options['index_strategy']
            );
        }

        if ($options['expanded']) {
            // Load choices already if expanded
            $values = $options['choice_list']->getValues();
            $labels = $options['choice_list']->getLabels();

            foreach ($values as $i => $value) {
                if ($options['multiple']) {
                    $builder->add((string) $i, 'checkbox', array(
                        'value'     => $value,
                        'label'     => $labels[$i],
                        // The user can check 0 or more checkboxes. If required
                        // is true, he is required to check all of them.
                        'required'  => false,
                        'translation_domain' => $options['translation_domain'],
                    ));
                } else {
                    $builder->add((string) $i, 'radio', array(
                        'value' => $value,
                        'label' => $labels[$i],
                        'translation_domain' => $options['translation_domain'],
                    ));
                }
            }
        }

        // empty value
        if ($options['multiple'] || $options['expanded']) {
            // never use and empty value for these cases
            $emptyValue = null;
        } elseif (false === $options['empty_value']) {
            // an empty value should be added but the user decided otherwise
            $emptyValue = null;
        } elseif (null === $options['empty_value']) {
            // user did not made a decision, so we put a blank empty value
            $emptyValue = $options['required'] ? null : '';
        } else {
            // empty value has been set explicitly
            $emptyValue = $options['empty_value'];
        }

        $builder
            ->setAttribute('choice_list', $options['choice_list'])
            ->setAttribute('preferred_choices', $options['preferred_choices'])
            ->setAttribute('multiple', $options['multiple'])
            ->setAttribute('expanded', $options['expanded'])
            ->setAttribute('required', $options['required'])
            ->setAttribute('empty_value', $emptyValue)
        ;

        if ($options['expanded']) {
            if ($options['multiple']) {
                $builder->appendClientTransformer(new ChoicesToBooleanArrayTransformer($options['choice_list']));
            } else {
                $builder
                    ->appendClientTransformer(new ChoiceToBooleanArrayTransformer($options['choice_list']))
                    ->addEventSubscriber(new FixRadioInputListener($options['choice_list']), 10)
                ;
            }
        } else {
            if ($options['multiple']) {
                $builder->appendClientTransformer(new ChoicesToValuesTransformer($options['choice_list']));
            } else {
                $builder->appendClientTransformer(new ChoiceToValueTransformer($options['choice_list']));
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form)
    {
        $choiceList = $form->getAttribute('choice_list');

        $view
            ->set('multiple', $form->getAttribute('multiple'))
            ->set('expanded', $form->getAttribute('expanded'))
            ->set('preferred_choices', $choiceList->getPreferredValueHierarchy())
            ->set('choices', $choiceList->getRemainingValueHierarchy())
            ->set('choice_labels', $choiceList->getLabels())
            ->set('separator', '-------------------')
            ->set('empty_value', $form->getAttribute('empty_value'))
        ;

        if ($view->get('multiple') && !$view->get('expanded')) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->set('full_name', $view->get('full_name').'[]');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions(array $options)
    {
        $multiple = isset($options['multiple']) && $options['multiple'];
        $expanded = isset($options['expanded']) && $options['expanded'];

        return array(
            'multiple'          => false,
            'expanded'          => false,
            'choice_list'       => null,
            'choices'           => array(),
            'preferred_choices' => array(),
            'value_strategy'    => ChoiceList::GENERATE,
            'index_strategy'    => ChoiceList::GENERATE,
            'empty_data'        => $multiple || $expanded ? array() : '',
            'empty_value'       => $multiple || $expanded || !isset($options['empty_value']) ? null : '',
            'error_bubbling'    => false,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(array $options)
    {
        return $options['expanded'] ? 'form' : 'field';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'choice';
    }
}
