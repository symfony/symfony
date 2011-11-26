<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Util\VirtualFormAwareIterator;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class PropertyPathMapper implements DataMapperInterface
{
    /**
     * Stores the class that the data of this form must be instances of
     * @var string
     */
    private $dataClass;

    public function __construct($dataClass = null)
    {
        $this->dataClass = $dataClass;
    }

    /**
     *
     * @param dataClass $data
     * @param array $forms
     *
     * @throws UnexpectedTypeException if the type of the data parameter is not supported
     */
    public function mapDataToForms($data, array $forms)
    {
        if (!empty($data) && !is_array($data) && !is_object($data)) {
            throw new UnexpectedTypeException($data, 'Object, array or empty');
        }

        if (!empty($data)) {
            if (null !== $this->dataClass && !$data instanceof $this->dataClass) {
                throw new UnexpectedTypeException($data, $this->dataClass);
            }

            $iterator = new VirtualFormAwareIterator($forms);
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
        $iterator = new VirtualFormAwareIterator($forms);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $form) {
            $this->mapFormToData($form, $data);
        }
    }

    public function mapFormToData(FormInterface $form, &$data)
    {
        if ($form->getAttribute('property_path') !== null && $form->isSynchronized()) {
            $propertyPath = $form->getAttribute('property_path');

            // If the data is identical to the value in $data, we are
            // dealing with a reference
            $isReference = $form->getData() === $propertyPath->getValue($data);
            $byReference = $form->getAttribute('by_reference');

            if (!(is_object($data) && $isReference && $byReference)) {
                $propertyPath->setValue($data, $form->getData());
            }
        }
    }
}
