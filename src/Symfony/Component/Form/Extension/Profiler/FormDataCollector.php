<?php


namespace Symfony\Component\Form\Extension\Profiler;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;

class FormDataCollector implements FormDataCollectorInterface, LateDataCollectorInterface
{
    private $dataExtractor;
    private $dataByForm = array();
    private $dataByView = array();
    private $formsByView = array();

    private $forms = array();
    private $nbErrors = 0;

    public function __construct(FormDataExtractorInterface $dataExtractor)
    {
        $this->dataExtractor = $dataExtractor;
    }

    /**
     * Collects data as late as possible.
     *
     * @return ProfileDataInterface
     */
    public function getCollectedData()
    {
        return new FormData($this->forms, $this->nbErrors);
    }

    /**
     * Listener for the {@link FormEvents::POST_SET_DATA} event.
     *
     * @param FormEvent $event The event object
     */
    public function postSetData(FormEvent $event)
    {
        if ($event->getForm()->isRoot()) {
            // Collect basic information about each form
            $this->collectConfiguration($event->getForm());

            // Collect the default data
            $this->collectDefaultData($event->getForm());
        }
    }

    /**
     * Listener for the {@link FormEvents::POST_SUBMIT} event.
     *
     * @param FormEvent $event The event object
     */
    public function postSubmit(FormEvent $event)
    {
        if ($event->getForm()->isRoot()) {
            // Collect the submitted data of each form
            $this->collectSubmittedData($event->getForm());

            // Assemble a form tree
            // This is done again after the view is built, but we need it here as the view is not always created.
            $this->buildPreliminaryFormTree($event->getForm());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function collectConfiguration(FormInterface $form)
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
    protected function collectDefaultData(FormInterface $form)
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
    protected function collectSubmittedData(FormInterface $form)
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
            $this->nbErrors += count($this->dataByForm[$hash]['errors']);
        }

        foreach ($form as $child) {
            $this->collectSubmittedData($child);
        }
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
    private function buildPreliminaryFormTree(FormInterface $form)
    {
        $this->forms[spl_object_hash($form)] = $this->recursiveBuildPreliminaryFormTree($form);
    }

    /**
     * {@inheritdoc}
     */
    public function buildFinalFormTree(FormInterface $form, FormView $view)
    {
        $this->forms[spl_object_hash($form)] = $this->recursiveBuildFinalFormTree($form, $view);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            // High priority in order to be called as soon as possible
            FormEvents::POST_SET_DATA => array('postSetData', 255),
            // Low priority in order to be called as late as possible
            FormEvents::POST_SUBMIT => array('postSubmit', -255),
        );
    }

    private function recursiveBuildPreliminaryFormTree(FormInterface $form)
    {
        $hash = spl_object_hash($form);

        $output = array_replace(
            array('name' => $form->getName(), 'children' => array()),
            isset($this->dataByForm[$hash]) ? $this->dataByForm[$hash] : array()
        );

        foreach ($form as $name => $child) {
            $output['children'][spl_object_hash($child)] = $this->recursiveBuildPreliminaryFormTree($child);
        }
        return $output;
    }

    private function recursiveBuildFinalFormTree(FormInterface $form = null, FormView $view)
    {
        $viewHash = spl_object_hash($view);
        $formHash = null;
        $formName = null;

        if (null !== $form) {
            $formHash = spl_object_hash($form);
            $formName = $form->getName();
        } elseif (isset($this->formsByView[$viewHash])) {
            // The FormInterface instance of the CSRF token is never contained in
            // the FormInterface tree of the form, so we need to get the
            // corresponding FormInterface instance for its view in a different way
            $formHash = $this->formsByView[$viewHash];
        }

        $output = array_replace(
            array('name' => $formName, 'children' => array()),
            isset($this->dataByView[$viewHash]) ? $this->dataByView[$viewHash] : array(),
            null !== $formHash && isset($this->dataByForm[$formHash]) ? $this->dataByForm[$formHash] : array()
        );

        foreach ($view->children as $name => $childView) {
            // The CSRF token, for example, is never added to the form tree.
            // It is only present in the view.
            $childForm = null !== $form && $form->has($name) ? $form->get($name) : null;

            $childHash = null !== $childForm?spl_object_hash($childForm):spl_object_hash($childView);
            $output['children'][$childHash] = array_replace($this->recursiveBuildFinalFormTree($childForm, $childView), array('name' => $name));
        }

        return $output;
    }

}