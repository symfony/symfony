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
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Caster\StubCaster;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Data collector for {@link FormInterface} instances.
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @final
 */
class FormDataCollector extends DataCollector implements FormDataCollectorInterface
{
    /**
     * Stores the collected data per {@link FormInterface} instance.
     *
     * Uses the hashes of the forms as keys. This is preferable over using
     * {@link \SplObjectStorage}, because in this way no references are kept
     * to the {@link FormInterface} instances.
     */
    private array $dataByForm;

    /**
     * Stores the collected data per {@link FormView} instance.
     *
     * Uses the hashes of the views as keys. This is preferable over using
     * {@link \SplObjectStorage}, because in this way no references are kept
     * to the {@link FormView} instances.
     */
    private array $dataByView;

    /**
     * Connects {@link FormView} with {@link FormInterface} instances.
     *
     * Uses the hashes of the views as keys and the hashes of the forms as
     * values. This is preferable over storing the objects directly, because
     * this way they can safely be discarded by the GC.
     */
    private array $formsByView;

    public function __construct(
        private FormDataExtractorInterface $dataExtractor,
    ) {
        if (!class_exists(ClassStub::class)) {
            throw new \LogicException(sprintf('The VarDumper component is needed for using the "%s" class. Install symfony/var-dumper version 3.4 or above.', __CLASS__));
        }

        $this->reset();
    }

    /**
     * Does nothing. The data is collected during the form event listeners.
     */
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function reset(): void
    {
        $this->data = [
            'forms' => [],
            'forms_by_hash' => [],
            'nb_errors' => 0,
        ];
    }

    public function associateFormWithView(FormInterface $form, FormView $view): void
    {
        $this->formsByView[spl_object_hash($view)] = spl_object_hash($form);
    }

    public function collectConfiguration(FormInterface $form): void
    {
        $hash = spl_object_hash($form);

        if (!isset($this->dataByForm[$hash])) {
            $this->dataByForm[$hash] = [];
        }

        $this->dataByForm[$hash] = array_replace(
            $this->dataByForm[$hash],
            $this->dataExtractor->extractConfiguration($form)
        );

        foreach ($form as $child) {
            $this->collectConfiguration($child);
        }
    }

    public function collectDefaultData(FormInterface $form): void
    {
        $hash = spl_object_hash($form);

        if (!isset($this->dataByForm[$hash])) {
            // field was created by form event
            $this->collectConfiguration($form);
        }

        $this->dataByForm[$hash] = array_replace(
            $this->dataByForm[$hash],
            $this->dataExtractor->extractDefaultData($form)
        );

        foreach ($form as $child) {
            $this->collectDefaultData($child);
        }
    }

    public function collectSubmittedData(FormInterface $form): void
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
            $this->data['nb_errors'] += \count($this->dataByForm[$hash]['errors']);
        }

        foreach ($form as $child) {
            $this->collectSubmittedData($child);

            // Expand current form if there are children with errors
            if (empty($this->dataByForm[$hash]['has_children_error'])) {
                $childData = $this->dataByForm[spl_object_hash($child)];
                $this->dataByForm[$hash]['has_children_error'] = !empty($childData['has_children_error']) || !empty($childData['errors']);
            }
        }
    }

    public function collectViewVariables(FormView $view): void
    {
        $hash = spl_object_hash($view);

        if (!isset($this->dataByView[$hash])) {
            $this->dataByView[$hash] = [];
        }

        $this->dataByView[$hash] = array_replace(
            $this->dataByView[$hash],
            $this->dataExtractor->extractViewVariables($view)
        );

        foreach ($view->children as $child) {
            $this->collectViewVariables($child);
        }
    }

    public function buildPreliminaryFormTree(FormInterface $form): void
    {
        $this->data['forms'][$form->getName()] = &$this->recursiveBuildPreliminaryFormTree($form, $this->data['forms_by_hash']);
    }

    public function buildFinalFormTree(FormInterface $form, FormView $view): void
    {
        $this->data['forms'][$form->getName()] = &$this->recursiveBuildFinalFormTree($form, $view, $this->data['forms_by_hash']);
    }

    public function getName(): string
    {
        return 'form';
    }

    public function getData(): array|Data
    {
        return $this->data;
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        foreach ($this->data['forms_by_hash'] as &$form) {
            if (isset($form['type_class']) && !$form['type_class'] instanceof ClassStub) {
                $form['type_class'] = new ClassStub($form['type_class']);
            }
        }

        $this->data = $this->cloneVar($this->data);

        return parent::__sleep();
    }

    protected function getCasters(): array
    {
        return parent::getCasters() + [
            \Exception::class => static function (\Exception $e, array $a, Stub $s) {
                foreach (["\0Exception\0previous", "\0Exception\0trace"] as $k) {
                    if (isset($a[$k])) {
                        unset($a[$k]);
                        ++$s->cut;
                    }
                }

                return $a;
            },
            FormInterface::class => static fn (FormInterface $f, array $a) => [
                Caster::PREFIX_VIRTUAL.'name' => $f->getName(),
                Caster::PREFIX_VIRTUAL.'type_class' => new ClassStub($f->getConfig()->getType()->getInnerType()::class),
            ],
            FormView::class => StubCaster::cutInternals(...),
            ConstraintViolationInterface::class => static fn (ConstraintViolationInterface $v, array $a) => [
                Caster::PREFIX_VIRTUAL.'root' => $v->getRoot(),
                Caster::PREFIX_VIRTUAL.'path' => $v->getPropertyPath(),
                Caster::PREFIX_VIRTUAL.'value' => $v->getInvalidValue(),
            ],
        ];
    }

    private function &recursiveBuildPreliminaryFormTree(FormInterface $form, array &$outputByHash): array
    {
        $hash = spl_object_hash($form);

        $output = &$outputByHash[$hash];
        $output = $this->dataByForm[$hash]
            ?? [];

        $output['children'] = [];

        foreach ($form as $name => $child) {
            $output['children'][$name] = &$this->recursiveBuildPreliminaryFormTree($child, $outputByHash);
        }

        return $output;
    }

    private function &recursiveBuildFinalFormTree(?FormInterface $form, FormView $view, array &$outputByHash): array
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
        if (null !== $formHash) {
            $output = &$outputByHash[$formHash];
        }

        $output = $this->dataByView[$viewHash]
            ?? [];

        if (null !== $formHash) {
            $output = array_replace(
                $output,
                $this->dataByForm[$formHash]
                    ?? []
            );
        }

        $output['children'] = [];

        foreach ($view->children as $name => $childView) {
            // The CSRF token, for example, is never added to the form tree.
            // It is only present in the view.
            $childForm = $form?->has($name) ? $form->get($name) : null;

            $output['children'][$name] = &$this->recursiveBuildFinalFormTree($childForm, $childView, $outputByHash);
        }

        return $output;
    }
}
