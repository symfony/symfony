<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\EventListener\FixRadioInputListener;
use Symfony\Component\Form\Renderer\ThemeRendererInterface;
use Symfony\Component\Form\DataTransformer\ScalarToChoiceTransformer;
use Symfony\Component\Form\DataTransformer\ScalarToBooleanChoicesTransformer;
use Symfony\Component\Form\DataTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\DataTransformer\ArrayToBooleanChoicesTransformer;

class ChoiceType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        if (!$options['choices'] && !$options['choice_list']) {
            throw new FormException('Either the option "choices" or "choice_list" is required');
        }

        if (!$options['choice_list']) {
            $options['choice_list'] = new ArrayChoiceList($options['choices']);
        }

        if ($options['expanded']) {
            // Load choices already if expanded
            $options['choices'] = $options['choice_list']->getChoices();

            foreach ($options['choices'] as $choice => $value) {
                if ($options['multiple']) {
                    $builder->add((string)$choice, 'checkbox', array('value' => $choice));
                } else {
                    $builder->add((string)$choice, 'radio', array('value' => $choice));
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

    public function buildRenderer(ThemeRendererInterface $renderer, FormInterface $form)
    {
        $choices = $form->getAttribute('choice_list')->getChoices();
        $preferred = array_flip($form->getAttribute('preferred_choices'));

        $renderer->setVar('multiple', $form->getAttribute('multiple'));
        $renderer->setVar('expanded', $form->getAttribute('expanded'));
        $renderer->setVar('preferred_choices', array_intersect_key($choices, $preferred));
        $renderer->setVar('choices', array_diff_key($choices, $preferred));
        $renderer->setVar('separator', '-------------------');
        $renderer->setVar('empty_value', '');

        if ($renderer->getVar('multiple') && !$renderer->getVar('expanded')) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $renderer->setVar('name', $renderer->getVar('name').'[]');
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'multiple' => false,
            'expanded' => false,
            'choice_list' => null,
            'choices' => array(),
            'preferred_choices' => array(),
            'csrf_protection' => false,
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