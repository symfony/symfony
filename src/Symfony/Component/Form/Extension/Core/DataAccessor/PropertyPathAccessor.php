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
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
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
    private $dataAccessor;

    /**
     * @param DataAccessorInterface|callable $dataAccessor The chain accessor instance or a callable that should return it
     */
    public function __construct(PropertyAccessorInterface $propertyAccessor = null, $dataAccessor = null)
    {
        if (null !== $dataAccessor && !\is_callable($dataAccessor) && !$dataAccessor instanceof DataAccessorInterface) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be an instance of "%s" or a callable, "%s" given.', __METHOD__, DataAccessorInterface::class, get_debug_type($dataAccessor)));
        }

        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->dataAccessor = $dataAccessor ?? $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($data, FormInterface $form)
    {
        if (null === $propertyPath = $form->getPropertyPath()) {
            throw new AccessException('Unable to read from the given form data as no property path is defined.');
        }

        return $this->getPropertyValue($data, $propertyPath);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(&$data, $propertyValue, FormInterface $form): void
    {
        if (null === $propertyPath = $form->getPropertyPath()) {
            throw new AccessException('Unable to write the given value as no property path is defined.');
        }

        $dataAccessor = \is_callable($this->dataAccessor) ? ($this->dataAccessor)() : $this->dataAccessor;

        // If the field is of type DateTimeInterface and the data is the same skip the update to
        // keep the original object hash
        if ($propertyValue instanceof \DateTimeInterface && $propertyValue == $dataAccessor->getValue($data, $form)) {
            return;
        }

        // If the data is identical to the value in $data, we are
        // dealing with a reference
        if (!\is_object($data) || !$form->getConfig()->getByReference() || $propertyValue !== $dataAccessor->getValue($data, $form)) {
            $this->propertyAccessor->setValue($data, $propertyPath, $propertyValue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($data, FormInterface $form): bool
    {
        return null !== $form->getPropertyPath();
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($data, FormInterface $form): bool
    {
        return null !== $form->getPropertyPath();
    }

    private function getPropertyValue($data, PropertyPathInterface $propertyPath)
    {
        try {
            return $this->propertyAccessor->getValue($data, $propertyPath);
        } catch (PropertyAccessException $e) {
            if (\is_array($data) && $e instanceof NoSuchIndexException) {
                return null;
            }

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
