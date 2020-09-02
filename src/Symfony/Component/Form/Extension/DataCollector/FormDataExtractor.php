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
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Default implementation of {@link FormDataExtractorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormDataExtractor implements FormDataExtractorInterface
{
    /**
     * Constructs a new data extractor.
     */
    public function __construct(ValueExporter $valueExporter = null, $triggerDeprecationNotice = true)
    {
        if (null !== $valueExporter && $triggerDeprecationNotice) {
            @trigger_error('Passing a ValueExporter instance to '.__METHOD__.'() is deprecated in version 3.2 and will be removed in 4.0.', \E_USER_DEPRECATED);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function extractConfiguration(FormInterface $form)
    {
        $data = [
            'id' => $this->buildId($form),
            'name' => $form->getName(),
            'type_class' => \get_class($form->getConfig()->getType()->getInnerType()),
            'synchronized' => $form->isSynchronized(),
            'passed_options' => [],
            'resolved_options' => [],
        ];

        foreach ($form->getConfig()->getAttribute('data_collector/passed_options', []) as $option => $value) {
            $data['passed_options'][$option] = $value;
        }

        foreach ($form->getConfig()->getOptions() as $option => $value) {
            $data['resolved_options'][$option] = $value;
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
        $data = [
            'default_data' => [
                'norm' => $form->getNormData(),
            ],
            'submitted_data' => [],
        ];

        if ($form->getData() !== $form->getNormData()) {
            $data['default_data']['model'] = $form->getData();
        }

        if ($form->getViewData() !== $form->getNormData()) {
            $data['default_data']['view'] = $form->getViewData();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function extractSubmittedData(FormInterface $form)
    {
        $data = [
            'submitted_data' => [
                'norm' => $form->getNormData(),
            ],
            'errors' => [],
        ];

        if ($form->getViewData() !== $form->getNormData()) {
            $data['submitted_data']['view'] = $form->getViewData();
        }

        if ($form->getData() !== $form->getNormData()) {
            $data['submitted_data']['model'] = $form->getData();
        }

        foreach ($form->getErrors() as $error) {
            $errorData = [
                'message' => $error->getMessage(),
                'origin' => \is_object($error->getOrigin())
                    ? spl_object_hash($error->getOrigin())
                    : null,
                'trace' => [],
            ];

            $cause = $error->getCause();

            while (null !== $cause) {
                if ($cause instanceof ConstraintViolationInterface) {
                    $errorData['trace'][] = $cause;
                    $cause = method_exists($cause, 'getCause') ? $cause->getCause() : null;

                    continue;
                }

                if ($cause instanceof \Exception) {
                    $errorData['trace'][] = $cause;
                    $cause = $cause->getPrevious();

                    continue;
                }

                $errorData['trace'][] = $cause;

                break;
            }

            $data['errors'][] = $errorData;
        }

        $data['synchronized'] = $form->isSynchronized();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function extractViewVariables(FormView $view)
    {
        $data = [
            'id' => isset($view->vars['id']) ? $view->vars['id'] : null,
            'name' => isset($view->vars['name']) ? $view->vars['name'] : null,
            'view_vars' => [],
        ];

        foreach ($view->vars as $varName => $value) {
            $data['view_vars'][$varName] = $value;
        }

        ksort($data['view_vars']);

        return $data;
    }

    /**
     * Recursively builds an HTML ID for a form.
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
