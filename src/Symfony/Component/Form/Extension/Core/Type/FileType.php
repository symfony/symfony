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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FileType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Ensure that submitted data is always an uploaded file or an array of some
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $requestHandler = $form->getConfig()->getRequestHandler();

            if ($options['multiple']) {
                $data = array();
                $files = $event->getData();

                if (!is_array($files)) {
                    $files = array();
                }

                foreach ($files as $file) {
                    if ($requestHandler->isFileUpload($file)) {
                        $data[] = $file;
                    }
                }

                // Since the array is never considered empty in the view data format
                // on submission, we need to evaluate the configured empty data here
                if (array() === $data) {
                    $emptyData = $form->getConfig()->getEmptyData();
                    $data = $emptyData instanceof \Closure ? $emptyData($form, $data) : $emptyData;
                }

                $event->setData($data);
            } elseif (!$requestHandler->isFileUpload($event->getData())) {
                $event->setData(null);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
            $view->vars['attr']['multiple'] = 'multiple';
        }

        $view->vars = array_replace($view->vars, array(
            'type' => 'file',
            'value' => '',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['multipart'] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $dataClass = null;
        if (class_exists('Symfony\Component\HttpFoundation\File\File')) {
            $dataClass = function (Options $options) {
                return $options['multiple'] ? null : 'Symfony\Component\HttpFoundation\File\File';
            };
        }

        $emptyData = function (Options $options) {
            return $options['multiple'] ? array() : null;
        };

        $resolver->setDefaults(array(
            'compound' => false,
            'data_class' => $dataClass,
            'empty_data' => $emptyData,
            'multiple' => false,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'file';
    }
}
