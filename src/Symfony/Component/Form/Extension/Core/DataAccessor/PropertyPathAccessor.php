<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataAccessor;

use Symfony\Component\Form\DataAccessorInterface;
use Symfony\Component\Form\Exception\AccessException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\Exception\AccessException as PropertyAccessException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * Writes and reads values to/from an object or array using property path.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPathAccessor implements DataAccessorInterface
{
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(object|array $data, FormInterface $form): mixed
    {
        if (null === $propertyPath = $form->getPropertyPath()) {
            throw new AccessException('Unable to read from the given form data as no property path is defined.');
        }

        return $this->getPropertyValue($data, $propertyPath);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(object|array &$data, mixed $value, FormInterface $form): void
    {
        if (null === $propertyPath = $form->getPropertyPath()) {
            throw new AccessException('Unable to write the given value as no property path is defined.');
        }

        // If the field is of type DateTimeInterface and the data is the same skip the update to
        // keep the original object hash
        if ($value instanceof \DateTimeInterface && $value == $this->getPropertyValue($data, $propertyPath)) {
            return;
        }

        // If the data is identical to the value in $data, we are
        // dealing with a reference
        if (!\is_object($data) || !$form->getConfig()->getByReference() || $value !== $this->getPropertyValue($data, $propertyPath)) {
            $this->propertyAccessor->setValue($data, $propertyPath, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(object|array $data, FormInterface $form): bool
    {
        return null !== $form->getPropertyPath();
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(object|array $data, FormInterface $form): bool
    {
        return null !== $form->getPropertyPath();
    }

    private function getPropertyValue(object|array $data, PropertyPathInterface $propertyPath)
    {
        try {
            return $this->propertyAccessor->getValue($data, $propertyPath);
        } catch (PropertyAccessException $e) {
            if (!$e instanceof UninitializedPropertyException
                // For versions without UninitializedPropertyException check the exception message
                && (class_exists(UninitializedPropertyException::class) || false === strpos($e->getMessage(), 'You should initialize it'))
            ) {
                throw $e;
            }

            return null;
        }
    }
}
