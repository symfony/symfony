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
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Caster\StubCaster;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * Default implementation of {@link FormDataExtractorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormDataExtractor implements FormDataExtractorInterface
{
    /**
     * @var VarCloner
     */
    private $cloner;

    /**
     * Constructs a new data extractor.
     */
    public function __construct(ValueExporter $valueExporter = null, $triggerDeprecationNotice = true)
    {
        if (null !== $valueExporter && $triggerDeprecationNotice) {
            @trigger_error('Passing a ValueExporter instance to '.__METHOD__.'() is deprecated in version 3.2 and will be removed in 4.0.', E_USER_DEPRECATED);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function extractConfiguration(FormInterface $form)
    {
        $data = array(
            'id' => $this->buildId($form),
            'name' => $form->getName(),
            'type_class' => $this->cloneVar(new ClassStub(get_class($form->getConfig()->getType()->getInnerType()))),
            'synchronized' => $this->cloneVar($form->isSynchronized()),
            'passed_options' => array(),
            'resolved_options' => array(),
        );

        foreach ($form->getConfig()->getAttribute('data_collector/passed_options', array()) as $option => $value) {
            $data['passed_options'][$option] = $this->cloneVar($value);
        }

        foreach ($form->getConfig()->getOptions() as $option => $value) {
            $data['resolved_options'][$option] = $this->cloneVar($value);
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
                'norm' => $this->cloneVar($form->getNormData()),
            ),
            'submitted_data' => array(),
        );

        if ($form->getData() !== $form->getNormData()) {
            $data['default_data']['model'] = $this->cloneVar($form->getData());
        }

        if ($form->getViewData() !== $form->getNormData()) {
            $data['default_data']['view'] = $this->cloneVar($form->getViewData());
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
                'norm' => $this->cloneVar($form->getNormData()),
            ),
            'errors' => array(),
        );

        if ($form->getViewData() !== $form->getNormData()) {
            $data['submitted_data']['view'] = $this->cloneVar($form->getViewData());
        }

        if ($form->getData() !== $form->getNormData()) {
            $data['submitted_data']['model'] = $this->cloneVar($form->getData());
        }

        foreach ($form->getErrors() as $error) {
            $errorData = array(
                'message' => $error->getMessage(),
                'origin' => is_object($error->getOrigin())
                    ? spl_object_hash($error->getOrigin())
                    : null,
                'trace' => array(),
            );

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

            if ($errorData['trace']) {
                $errorData['trace'] = $this->cloneVar($errorData['trace']);
            }
            $data['errors'][] = $errorData;
        }

        $data['synchronized'] = $this->cloneVar($form->isSynchronized());

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
        if (!isset($data['id'])) {
            $data['id'] = isset($view->vars['id']) ? $view->vars['id'] : null;
        }

        if (!isset($data['name'])) {
            $data['name'] = isset($view->vars['name']) ? $view->vars['name'] : null;
        }

        foreach ($view->vars as $varName => $value) {
            $data['view_vars'][$varName] = $this->cloneVar($value);
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

    /**
     * Converts the variable into a serializable Data instance.
     *
     * @param mixed $var
     *
     * @return Data
     */
    private function cloneVar($var)
    {
        if (null === $this->cloner) {
            $this->cloner = new VarCloner();
            $this->cloner->addCasters(array(
                Stub::class => function (Stub $v, array $a, Stub $s, $isNested) {
                    return $isNested ? $a : StubCaster::castStub($v, $a, $s, true);
                },
                \Exception::class => function (\Exception $e, array $a, Stub $s) {
                    if (isset($a[$k = "\0Exception\0previous"])) {
                        unset($a[$k]);
                        ++$s->cut;
                    }

                    return $a;
                },
                FormInterface::class => function (FormInterface $f, array $a) {
                    return array(
                        Caster::PREFIX_VIRTUAL.'name' => $f->getName(),
                        Caster::PREFIX_VIRTUAL.'type_class' => new ClassStub(get_class($f->getConfig()->getType()->getInnerType())),
                    );
                },
                ConstraintViolationInterface::class => function (ConstraintViolationInterface $v, array $a) {
                    return array(
                        Caster::PREFIX_VIRTUAL.'root' => $v->getRoot(),
                        Caster::PREFIX_VIRTUAL.'path' => $v->getPropertyPath(),
                        Caster::PREFIX_VIRTUAL.'value' => $v->getInvalidValue(),
                    );
                },
            ));
        }

        return $this->cloner->cloneVar($var);
    }
}
