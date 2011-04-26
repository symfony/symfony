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
use Symfony\Component\Form\Extension\Core\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\Extension\Core\EventListener\FixRadioInputListener;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\DataTransformer\ScalarToChoiceTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ScalarToBooleanChoicesTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToBooleanChoicesTransformer;

class ChoiceType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        if (!$options['choices'] && !$options['choice_list']) {
            throw new FormException('Either the option "choices" or "choice_list" is required');
        }

        if ($options['choice_list'] && !$options['choice_list'] instanceof ChoiceListInterface) {
            throw new FormException('The "choice_list" must be an instance of "Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface".');
        }

        if (!$options['choice_list']) {
            $options['choice_list'] = new ArrayChoiceList($options['choices']);
        }

        if ($options['expanded']) {
            // Load choices already if expanded
            $options['choices'] = $options['choice_list']->getChoices();

            foreach ($options['choices'] as $choice => $value) {
                if ($options['multiple']) {
                    $builder->add((string)$choice, 'checkbox', array(
                        'value' => $choice,
                        'label' => $value,
                        // The user can check 0 or more checkboxes. If required
                        // is true, he is required to check all of them.
                        'required' => false,
                    ));
                } else {
                    $builder->add((string)$choice, 'radio', array(
                        'value' => $choice,
                        'label' => $value,
                    ));
                }
            }
        }

        $builder->setAttribute('choice_list', $options['choice_list'])
            ->setAttribute('preferred_choices', $options['preferred_choices'])
            ->setAttribute('multiple', $options['multiple'])
            ->setAttribute('expanded', $options['expanded']);

        if ($options['expanded']) {
            if ($options['multiple']) {
                $builder->appendClientTransformer(new ArrayToBooleanChoicesTransformer($options['choice_list']));
            } else {
                $builder->appendClientTransformer(new ScalarToBooleanChoicesTransformer($options['choice_list']));
                $builder->addEventSubscriber(new FixRadioInputListener(), 10);
            }
        } else {
            if ($options['multiple']) {
                $builder->appendClientTransformer(new ArrayToChoicesTransformer());
            } else {
                $builder->appendClientTransformer(new ScalarToChoiceTransformer());
            }
        }

    }

    public function buildView(FormView $view, FormInterface $form)
    {
        $choices = $form->getAttribute('choice_list')->getChoices();
        $preferred = array_flip($form->getAttribute('preferred_choices'));

        $view->set('multiple', $form->getAttribute('multiple'));
        $view->set('expanded', $form->getAttribute('expanded'));
        $view->set('preferred_choices', array_intersect_key($choices, $preferred));
        $view->set('choices', array_diff_key($choices, $preferred));
        $view->set('separator', '-------------------');
        $view->set('empty_value', '');

        if ($view->get('multiple') && !$view->get('expanded')) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->set('name', $view->get('name').'[]');
        }
    }

    public function getDefaultOptions(array $options)
    {
        $multiple = isset($options['multiple']) && $options['multiple'];
        $expanded = isset($options['expanded']) && $options['expanded'];

        return array(
            'multiple' => false,
            'expanded' => false,
            'choice_list' => null,
            'choices' => array(),
            'preferred_choices' => array(),
            'csrf_protection' => false,
            'empty_data' => $multiple || $expanded ? array() : '',
            'error_bubbling' => false,
        );
    }

    public function getParent(array $options)
    {
        return $options['expanded'] ? 'form' : 'field';
    }

    public function getName()
    {
        return 'choice';
    }
}
