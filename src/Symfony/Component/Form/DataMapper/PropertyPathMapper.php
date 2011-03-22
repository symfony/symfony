<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DataMapper;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\VirtualFormIterator;
use Symfony\Component\Form\Exception\FormException;

class PropertyPathMapper implements DataMapperInterface
{
    /**
     * Stores the class that the data of this form must be instances of
     * @var string
     */
    private $dataClass;

    /**
     * Stores the constructor closure for creating new domain object instances
     * @var \Closure
     */
    private $dataConstructor;

    public function __construct($dataClass = null, $dataConstructor = null)
    {
        $this->dataClass = $dataClass;
        $this->dataConstructor = $dataConstructor;
    }

    public function createEmptyData()
    {
        if ($this->dataConstructor) {
            $constructor = $this->dataConstructor;

            return $constructor();
        } else if ($this->dataClass) {
            $class = $this->dataClass;

            return new $class();
        }

        return array();
    }

    public function mapDataToForms($data, array $forms)
    {
        if (!empty($data) && !is_array($data) && !is_object($data)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type object or array, %s given', gettype($data)));
        }

        if (!empty($data)) {
            if ($this->dataClass && !$data instanceof $this->dataClass) {
                throw new FormException(sprintf('Form data should be instance of %s', $this->dataClass));
            }

            $iterator = new VirtualFormIterator($forms);
            $iterator = new \RecursiveIteratorIterator($iterator);

            foreach ($iterator as $form) {
                $this->mapDataToForm($data, $form);
            }
        }
    }

    public function mapDataToForm($data, FormInterface $form)
    {
        if (!empty($data)) {
            if ($form->getAttribute('property_path') !== null) {
                $form->setData($form->getAttribute('property_path')->getValue($data));
            }
        }
    }

    public function mapFormsToData(array $forms, &$data)
    {
        $iterator = new VirtualFormIterator($forms);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $form) {
            $isReference = false;

            // If the data is identical to the value in $data, we are
            // dealing with a reference
            if ($form->getAttribute('property_path') !== null) {
                $isReference = $form->getData() === $form->getAttribute('property_path')->getValue($data);
            }

            // Don't write into $data if $data is an object,
            // $isReference is true (see above) and the option "by_reference" is
            // true as well
            if (!is_object($data) || !$isReference || !$form->getAttribute('by_reference')) {
                $this->mapFormToData($form, $data);
            }
        }
    }

    public function mapFormToData(FormInterface $form, &$data)
    {
        if ($form->getAttribute('property_path') !== null) {
            $form->getAttribute('property_path')->setValue($data, $form->getData());
        }
    }
}