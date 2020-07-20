<?php

namespace Symfony\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\ExceptionInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormError;
use TypeError;

class AccessorMapper implements DataMapperInterface
{
    private $get;
    private $set;
    private $fallbackMapper;

    public function __construct(?\Closure $get, ?\Closure $set, DataMapperInterface $fallbackMapper)
    {
        $this->get = $get;
        $this->set = $set;
        $this->fallbackMapper = $fallbackMapper;
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($data, iterable $forms)
    {
        $empty = null === $data || [] === $data;

        if (!$empty && !\is_array($data) && !\is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        if (!$this->get) {
            $this->fallbackMapper->mapDataToForms($data, $forms);
            return;
        }

        foreach ($forms as $form) {
            $config = $form->getConfig();

            if (!$empty && $config->getMapped()) {
                $form->setData($this->getPropertyValue($data));
            } else {
                $form->setData($config->getData());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData(iterable $forms, &$data)
    {
        if (null === $data) {
            return;
        }

        if (!\is_array($data) && !\is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        if (!$this->set) {
            $this->fallbackMapper->mapFormsToData($forms, $data);
            return;
        }

        foreach ($forms as $form) {
            $config = $form->getConfig();

            // Write-back is disabled if the form is not synchronized (transformation failed),
            // if the form was not submitted and if the form is disabled (modification not allowed)
            if (null !== $this->set && $config->getMapped() && $form->isSubmitted() && $form->isSynchronized() && !$form->isDisabled()) {
                try {
                    $returnValue = ($this->set)($data, $form->getData());
                } catch (ExceptionInterface | TypeError $e) {
                    $form->addError(new FormError($e->getMessage()));
                    continue;
                }

                $type = is_object($returnValue) ? get_class($returnValue) : gettype($returnValue);

                if (
                    (is_scalar($data) && gettype($data) === $type)
                    || (is_array($data) && is_array($returnValue))
                    || (is_object($data) && $returnValue instanceof $type)) {
                    $data = $returnValue;
                }
            }
        }
    }

    private function getPropertyValue($data)
    {
        return $this->get ? ($this->get)($data) : null;
    }
}
