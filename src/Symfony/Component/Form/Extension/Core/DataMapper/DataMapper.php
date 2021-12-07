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

use Symfony\Component\Form\DataAccessorInterface;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\DataAccessor\CallbackAccessor;
use Symfony\Component\Form\Extension\Core\DataAccessor\ChainAccessor;
use Symfony\Component\Form\Extension\Core\DataAccessor\PropertyPathAccessor;

/**
 * Maps arrays/objects to/from forms using data accessors.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DataMapper implements DataMapperInterface
{
    private $dataAccessor;

    public function __construct(DataAccessorInterface $dataAccessor = null)
    {
        $this->dataAccessor = $dataAccessor ?? new ChainAccessor([
            new CallbackAccessor(),
            new PropertyPathAccessor(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms(mixed $data, \Traversable $forms): void
    {
        $empty = null === $data || [] === $data;

        if (!$empty && !\is_array($data) && !\is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        foreach ($forms as $form) {
            $config = $form->getConfig();

            if (!$empty && $config->getMapped() && $this->dataAccessor->isReadable($data, $form)) {
                $form->setData($this->dataAccessor->getValue($data, $form));
            } else {
                $form->setData($config->getData());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData(\Traversable $forms, mixed &$data): void
    {
        if (null === $data) {
            return;
        }

        if (!\is_array($data) && !\is_object($data)) {
            throw new UnexpectedTypeException($data, 'object, array or empty');
        }

        foreach ($forms as $form) {
            $config = $form->getConfig();

            // Write-back is disabled if the form is not synchronized (transformation failed),
            // if the form was not submitted and if the form is disabled (modification not allowed)
            if ($config->getMapped() && $form->isSubmitted() && $form->isSynchronized() && !$form->isDisabled() && $this->dataAccessor->isWritable($data, $form)) {
                $this->dataAccessor->setValue($data, $form->getData(), $form);
            }
        }
    }
}
