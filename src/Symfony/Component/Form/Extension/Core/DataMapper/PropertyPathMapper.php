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

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * A data mapper using property paths to read/write data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPathMapper implements DataMapperInterface
{
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * Creates a new property path mapper.
     *
     * @param PropertyAccessorInterface $propertyAccessor
     */
    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::getPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($data, $forms)
    {
        if (null === $data || array() === $data) {
            return;
        }

        if (!is_array($data) && !is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        foreach ($forms as $form) {
            $propertyPath = $form->getPropertyPath();
            $config = $form->getConfig();

            if (null !== $propertyPath && $config->getMapped()) {
                $form->setData($this->propertyAccessor->getValue($data, $propertyPath));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData($forms, &$data)
    {
        if (null === $data) {
            return;
        }

        if (!is_array($data) && !is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        foreach ($forms as $form) {
            $propertyPath = $form->getPropertyPath();
            $config = $form->getConfig();

            // Write-back is disabled if the form is not synchronized (transformation failed)
            // and if the form is disabled (modification not allowed)
            if (null !== $propertyPath && $config->getMapped() && $form->isSynchronized() && !$form->isDisabled()) {
                // If the data is identical to the value in $data, we are
                // dealing with a reference
                if (!is_object($data) || !$config->getByReference() || $form->getData() !== $this->propertyAccessor->getValue($data, $propertyPath)) {
                    $this->propertyAccessor->setValue($data, $propertyPath, $form->getData());
                }
            }
        }
    }
}
