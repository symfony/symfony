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

use Symfony\Component\Form\DataClassType;
use Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Default implementation of {@link FormDataExtractorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormDataExtractor implements FormDataExtractorInterface
{
    public function extractConfiguration(FormInterface $form): array
    {
        $data = [
            'id' => $this->buildId($form),
            'name' => $form->getName(),
            'type_class' => $this->getTypeClass($form->getConfig()->getType()),
            'synchronized' => $form->isSynchronized(),
            'passed_options' => [],
            'resolved_options' => [],
            'type_extensions' => $this->extractTypeExtensions($form),
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

    public function extractDefaultData(FormInterface $form): array
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

    public function extractSubmittedData(FormInterface $form): array
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
                    ? spl_object_id($error->getOrigin())
                    : null,
                'trace' => [],
            ];

            $cause = $error->getCause();

            while (null !== $cause) {
                if ($cause instanceof ConstraintViolationInterface) {
                    $errorData['trace'][] = $cause;
                    $cause = $cause->getCause();

                    continue;
                }

                $errorData['trace'][] = $cause;
                if ($cause instanceof \Exception) {
                    $cause = $cause->getPrevious();

                    continue;
                }

                break;
            }

            $data['errors'][] = $errorData;
        }

        $data['synchronized'] = $form->isSynchronized();

        return $data;
    }

    public function extractViewVariables(FormView $view): array
    {
        $data = [
            'id' => $view->vars['id'] ?? null,
            'name' => $view->vars['name'] ?? null,
            'view_vars' => [],
        ];

        foreach ($view->vars as $varName => $value) {
            $data['view_vars'][$varName] = $value;
        }

        ksort($data['view_vars']);

        return $data;
    }

    /**
     * Lists the type extensions applied to a form, from the root type down to its own type.
     *
     * @return list<array{type: class-string, extension: class-string}>
     */
    private function extractTypeExtensions(FormInterface $form): array
    {
        $extensions = [];

        for ($type = $form->getConfig()->getType(); null !== $type; $type = $type->getParent()) {
            $typeExtensions = [];
            foreach ($type->getTypeExtensions() as $extension) {
                // registered by the profiler itself on every form
                if ($extension instanceof DataCollectorTypeExtension) {
                    continue;
                }

                $typeExtensions[] = ['type' => $this->getTypeClass($type), 'extension' => $extension::class];
            }

            array_unshift($extensions, ...$typeExtensions);
        }

        return $extensions;
    }

    private function getTypeClass(ResolvedFormTypeInterface $type): string
    {
        $innerType = $type->getInnerType();

        return $innerType instanceof DataClassType ? $innerType->getDataClass() : $innerType::class;
    }

    /**
     * Recursively builds an HTML ID for a form.
     */
    private function buildId(FormInterface $form): string
    {
        $id = $form->getName();

        if (null !== $form->getParent()) {
            $id = $this->buildId($form->getParent()).'_'.$id;
        }

        return $id;
    }
}
