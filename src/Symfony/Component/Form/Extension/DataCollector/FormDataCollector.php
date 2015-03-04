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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Data collector for {@link \Symfony\Component\Form\FormInterface} instances.
 *
 * @since  2.4
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormDataCollector extends DataCollector implements FormDataCollectorInterface
{
    /**
     * @var FormDataExtractor
     */
    private $dataExtractor;

    /**
     * Stores the collected data per {@link FormInterface} instance.
     *
     * Uses the hashes of the forms as keys. This is preferable over using
     * {@link \SplObjectStorage}, because in this way no references are kept
     * to the {@link FormInterface} instances.
     *
     * @var array
     */
    private $dataByForm;

    /**
     * Stores the collected data per {@link FormView} instance.
     *
     * Uses the hashes of the views as keys. This is preferable over using
     * {@link \SplObjectStorage}, because in this way no references are kept
     * to the {@link FormView} instances.
     *
     * @var array
     */
    private $dataByView;

    /**
     * Connects {@link FormView} with {@link FormInterface} instances.
     *
     * Uses the hashes of the views as keys and the hashes of the forms as
     * values. This is preferable over storing the objects directly, because
     * this way they can safely be discarded by the GC.
     *
     * @var array
     */
    private $formsByView;

    public function __construct(FormDataExtractorInterface $dataExtractor)
    {
        $this->dataExtractor = $dataExtractor;
        $this->data = array(
            'forms' => array(),
            'forms_by_hash' => array(),
            'nb_errors' => 0,
        );
    }

    /**
     * Does nothing. The data is collected during the form event listeners.
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function associateFormWithView(FormInterface $form, FormView $view)
    {
        $this->formsByView[spl_object_hash($view)] = spl_object_hash($form);
    }

    /**
     * {@inheritdoc}
     */
    public function collectConfiguration(FormInterface $form)
    {
        $hash = spl_object_hash($form);

        if (!isset($this->dataByForm[$hash])) {
            $this->dataByForm[$hash] = array();
        }

        $this->dataByForm[$hash] = array_replace(
            $this->dataByForm[$hash],
            $this->dataExtractor->extractConfiguration($form)
        );

        foreach ($form as $child) {
            $this->collectConfiguration($child);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collectDefaultData(FormInterface $form)
    {
        $hash = spl_object_hash($form);

        if (!isset($this->dataByForm[$hash])) {
            $this->dataByForm[$hash] = array();
        }

        $this->dataByForm[$hash] = array_replace(
            $this->dataByForm[$hash],
            $this->dataExtractor->extractDefaultData($form)
        );

        foreach ($form as $child) {
            $this->collectDefaultData($child);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collectSubmittedData(FormInterface $form)
    {
        $hash = spl_object_hash($form);

        if (!isset($this->dataByForm[$hash])) {
            // field was created by form event
            $this->collectConfiguration($form);
            $this->collectDefaultData($form);
        }

        $this->dataByForm[$hash] = array_replace(
            $this->dataByForm[$hash],
            $this->dataExtractor->extractSubmittedData($form)
        );

        // Count errors
        if (isset($this->dataByForm[$hash]['errors'])) {
            $this->data['nb_errors'] += count($this->dataByForm[$hash]['errors']);
        }

        foreach ($form as $child) {
            $this->collectSubmittedData($child);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collectViewVariables(FormView $view)
    {
        $hash = spl_object_hash($view);

        if (!isset($this->dataByView[$hash])) {
            $this->dataByView[$hash] = array();
        }

        $this->dataByView[$hash] = array_replace(
            $this->dataByView[$hash],
            $this->dataExtractor->extractViewVariables($view)
        );

        foreach ($view->children as $child) {
            $this->collectViewVariables($child);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildPreliminaryFormTree(FormInterface $form)
    {
        $this->data['forms'][$form->getName()] = array();

        $this->recursiveBuildPreliminaryFormTree($form, $this->data['forms'][$form->getName()], $this->data['forms_by_hash']);
    }

    /**
     * {@inheritdoc}
     */
    public function buildFinalFormTree(FormInterface $form, FormView $view)
    {
        $this->data['forms'][$form->getName()] = array();

        $this->recursiveBuildFinalFormTree($form, $view, $this->data['forms'][$form->getName()], $this->data['forms_by_hash']);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }

    private function recursiveBuildPreliminaryFormTree(FormInterface $form, &$output = null, array &$outputByHash)
    {
        $hash = spl_object_hash($form);

        $output = isset($this->dataByForm[$hash])
            ? $this->dataByForm[$hash]
            : array();

        $outputByHash[$hash] = &$output;

        $output['children'] = array();

        foreach ($form as $name => $child) {
            $output['children'][$name] = array();

            $this->recursiveBuildPreliminaryFormTree($child, $output['children'][$name], $outputByHash);
        }
    }

    private function recursiveBuildFinalFormTree(FormInterface $form = null, FormView $view, &$output = null, array &$outputByHash)
    {
        $viewHash = spl_object_hash($view);
        $formHash = null;

        if (null !== $form) {
            $formHash = spl_object_hash($form);
        } elseif (isset($this->formsByView[$viewHash])) {
            // The FormInterface instance of the CSRF token is never contained in
            // the FormInterface tree of the form, so we need to get the
            // corresponding FormInterface instance for its view in a different way
            $formHash = $this->formsByView[$viewHash];
        }

        $output = isset($this->dataByView[$viewHash])
            ? $this->dataByView[$viewHash]
            : array();

        if (null !== $formHash) {
            $output = array_replace(
                $output,
                isset($this->dataByForm[$formHash])
                    ? $this->dataByForm[$formHash]
                    : array()
            );

            $outputByHash[$formHash] = &$output;
        }

        $output['children'] = array();

        foreach ($view->children as $name => $childView) {
            // The CSRF token, for example, is never added to the form tree.
            // It is only present in the view.
            $childForm = null !== $form && $form->has($name)
                ? $form->get($name)
                : null;

            $output['children'][$name] = array();

            $this->recursiveBuildFinalFormTree($childForm, $childView, $output['children'][$name], $outputByHash);
        }
    }
}
