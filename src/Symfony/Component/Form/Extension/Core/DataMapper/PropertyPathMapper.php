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
use Symfony\Component\Form\Util\VirtualFormAwareIterator;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class PropertyPathMapper implements DataMapperInterface
{
    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($data, array $forms)
    {
        if (null === $data || array() === $data) {
            return;
        }

        if (!is_array($data) && !is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        $iterator = new VirtualFormAwareIterator($forms);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $form) {
            /* @var FormInterface $form */
            $propertyPath = $form->getPropertyPath();
            $config = $form->getConfig();

            if (null !== $propertyPath && $config->getMapped()) {
                $form->setData($propertyPath->getValue($data));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData(array $forms, &$data)
    {
        if (null === $data) {
            return;
        }

        if (!is_array($data) && !is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        $iterator = new VirtualFormAwareIterator($forms);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $form) {
            /* @var FormInterface $form */
            $propertyPath = $form->getPropertyPath();
            $config = $form->getConfig();

            // Write-back is disabled if the form is not synchronized (transformation failed)
            // and if the form is disabled (modification not allowed)
            if (null !== $propertyPath && $config->getMapped() && $form->isSynchronized() && !$form->isDisabled()) {
                // If the data is identical to the value in $data, we are
                // dealing with a reference
                if (!is_object($data) || !$config->getByReference() || $form->getData() !== $propertyPath->getValue($data)) {
                    $propertyPath->setValue($data, $form->getData());
                }
            }
        }
    }
}
