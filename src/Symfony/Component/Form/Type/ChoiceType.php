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
use Symfony\Component\Form\ChoiceList\DefaultChoiceList;
use Symfony\Component\Form\EventListener\FixRadioInputListener;
use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\Form\DataTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\DataTransformer\ArrayToChoicesTransformer;

class ChoiceType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        if ($options['expanded']) {
            $choices = array_replace(
                $options['choice_list']->getPreferredChoices(),
                $options['choice_list']->getOtherChoices()
            );

            foreach ($choices as $choice => $value) {
                if ($options['multiple']) {
                    $builder->add((string)$choice, 'checkbox', array('value' => $choice));
                } else {
                    $builder->add((string)$choice, 'radio', array('value' => $choice));
                }
            }
        }

        $builder->setAttribute('choice_list', $options['choice_list'])
            ->setAttribute('multiple', $options['multiple'])
            ->setAttribute('expanded', $options['expanded']);

        if ($options['multiple'] && $options['expanded']) {
            $builder->setClientTransformer(new ArrayToChoicesTransformer($options['choice_list']));
        }

        if (!$options['multiple'] && $options['expanded']) {
            $builder->setClientTransformer(new ScalarToChoicesTransformer($options['choice_list']));
            $builder->addEventSubscriber(new FixRadioInputListener(), 10);
        }
    }

    public function buildRenderer(FormRendererInterface $renderer, FormInterface $form)
    {
        $choiceList = $form->getAttribute('choice_list');

        $renderer->setVar('multiple', $form->getAttribute('multiple'));
        $renderer->setVar('expanded', $form->getAttribute('expanded'));
        $renderer->setVar('choices', $choiceList->getOtherChoices());
        $renderer->setVar('preferred_choices', $choiceList->getPreferredChoices());
        $renderer->setVar('separator', '-------------------');
        $renderer->setVar('choice_list', $choiceList);
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
        $defaultOptions = array(
            'multiple' => false,
            'expanded' => false,
            'choices' => array(),
            'preferred_choices' => array(),
            'csrf_protection' => false,
            'choice_list' => null,
        );

        $options = array_replace($defaultOptions, $options);

        if (!isset($options['choice_list'])) {
            $defaultOptions['choice_list'] = new DefaultChoiceList(
                $options['choices'],
                $options['preferred_choices']
            );
        }

        return $defaultOptions;
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