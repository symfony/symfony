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
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Maps arrays/objects to/from forms using property paths.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPathMapper implements DataMapperInterface
{
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($data, $forms)
    {
        $empty = null === $data || [] === $data;

        if (!$empty && !\is_array($data) && !\is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        foreach ($forms as $form) {
            $propertyPath = $form->getPropertyPath();
            $config = $form->getConfig();

            if (!$empty && null !== $propertyPath && $config->getMapped()) {
                try {
                    $form->setData($this->propertyAccessor->getValue($data, $propertyPath));
                } catch (AccessException $e) {
                    // Skip unitialized properties on $data
                    $this->catchUninitializedPropertyException($e);
                }
            } else {
                $form->setData($config->getData());
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

        if (!\is_array($data) && !\is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        foreach ($forms as $form) {
            $propertyPath = $form->getPropertyPath();
            $config = $form->getConfig();

            // Write-back is disabled if the form is not synchronized (transformation failed),
            // if the form was not submitted and if the form is disabled (modification not allowed)
            if (null !== $propertyPath && $config->getMapped() && $form->isSubmitted() && $form->isSynchronized() && !$form->isDisabled()) {
                $propertyValue = $form->getData();
                // If the field is of type DateTimeInterface and the data is the same skip the update to
                // keep the original object hash
                if ($propertyValue instanceof \DateTimeInterface && $propertyValue == $this->getPropertyValue($data, $propertyPath)) {
                    continue;
                }

                // If the data is identical to the value in $data, we are
                // dealing with a reference
                if (!\is_object($data) || !$config->getByReference() || $propertyValue !== $this->getPropertyValue($data, $propertyPath)) {
                    $this->propertyAccessor->setValue($data, $propertyPath, $propertyValue);
                }
            }
        }
    }

    /**
     * Get the property value per PropertyAccessor.
     * Treat uninitialized properties as null.
     *
     * @param object|array                 $objectOrArray The object or array to traverse
     * @param string|PropertyPathInterface $propertyPath  The property path to read
     *
     * @return mixed The value at the end of the property path
     *
     * @throws Exception\InvalidArgumentException If the property path is invalid
     * @throws Exception\UnexpectedTypeException  If a value within the path is neither object nor array
     */
    private function getPropertyValue($data, $propertyPath)
    {
        try {
            return $this->propertyAccessor->getValue($data, $propertyPath);
        } catch (AccessException $e) {
            // The following line might be removed in future versions
            // See https://github.com/symfony/symfony/issues/36754
            $this->catchUninitializedPropertyException($e);

            return null;
        }
    }

    /**
     * Throw everything but UninitializedPropertyException.
     */
    private function catchUninitializedPropertyException(AccessException $e)
    {
        if (!$e instanceof UninitializedPropertyException
            // For versions without UninitializedPropertyException check the exception message
            && (class_exists(UninitializedPropertyException::class) || false === strpos($e->getMessage(), 'You should initialize it'))
        ) {
            throw $e;
        }
    }
}
