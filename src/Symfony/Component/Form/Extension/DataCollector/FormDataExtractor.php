<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpKernel\DataCollector\Util\ValueExporter;

/**
 * Default implementation of {@link FormDataExtractorInterface}.
 *
 * @since  2.4
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormDataExtractor implements FormDataExtractorInterface
{
    /**
     * @var ValueExporter
     */
    private $valueExporter;

    /**
     * Constructs a new data extractor.
     */
    public function __construct(ValueExporter $valueExporter = null)
    {
        $this->valueExporter = $valueExporter ?: new ValueExporter();
    }

    /**
     * {@inheritdoc}
     */
    public function extractConfiguration(FormInterface $form)
    {
        $data = array(
            'id' => $this->buildId($form),
            'type' => $form->getConfig()->getType()->getName(),
            'type_class' => get_class($form->getConfig()->getType()->getInnerType()),
            'synchronized' => $this->valueExporter->exportValue($form->isSynchronized()),
            'passed_options' => array(),
            'resolved_options' => array(),
        );

        foreach ($form->getConfig()->getAttribute('data_collector/passed_options', array()) as $option => $value) {
            $data['passed_options'][$option] = $this->valueExporter->exportValue($value);
        }

        foreach ($form->getConfig()->getOptions() as $option => $value) {
            $data['resolved_options'][$option] = $this->valueExporter->exportValue($value);
        }

        ksort($data['passed_options']);
        ksort($data['resolved_options']);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function extractDefaultData(FormInterface $form)
    {
        $data = array(
            'default_data' => array(
                'norm' => $this->valueExporter->exportValue($form->getNormData()),
            ),
            'submitted_data' => array(),
        );

        if ($form->getData() !== $form->getNormData()) {
            $data['default_data']['model'] = $this->valueExporter->exportValue($form->getData());
        }

        if ($form->getViewData() !== $form->getNormData()) {
            $data['default_data']['view'] = $this->valueExporter->exportValue($form->getViewData());
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function extractSubmittedData(FormInterface $form)
    {
        $data = array(
            'submitted_data' => array(
                'norm' => $this->valueExporter->exportValue($form->getNormData()),
            ),
            'errors' => array(),
        );

        if ($form->getViewData() !== $form->getNormData()) {
            $data['submitted_data']['view'] = $this->valueExporter->exportValue($form->getViewData());
        }

        if ($form->getData() !== $form->getNormData()) {
            $data['submitted_data']['model'] = $this->valueExporter->exportValue($form->getData());
        }

        foreach ($form->getErrors() as $error) {
            $data['errors'][] = array(
                'message' => $error->getMessage(),
            );
        }

        $data['synchronized'] = $this->valueExporter->exportValue($form->isSynchronized());

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function extractViewVariables(FormView $view)
    {
        $data = array();

        // Set the ID in case no FormInterface object was collected for this
        // view
        if (isset($view->vars['id'])) {
            $data['id'] = $view->vars['id'];
        }

        foreach ($view->vars as $varName => $value) {
            $data['view_vars'][$varName] = $this->valueExporter->exportValue($value);
        }

        ksort($data['view_vars']);

        return $data;
    }

    /**
     * Recursively builds an HTML ID for a form.
     *
     * @param FormInterface $form The form
     *
     * @return string The HTML ID
     */
    private function buildId(FormInterface $form)
    {
        $id = $form->getName();

        if (null !== $form->getParent()) {
            $id = $this->buildId($form->getParent()).'_'.$id;
        }

        return $id;
    }
}
