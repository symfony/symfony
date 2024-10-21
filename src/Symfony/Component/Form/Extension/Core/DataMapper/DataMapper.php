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

    public function __construct(?DataAccessorInterface $dataAccessor = null)
    {
        $this->dataAccessor = $dataAccessor ?? new ChainAccessor([
            new CallbackAccessor(),
            new PropertyPathAccessor(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($data, iterable $forms): void
    {
        if (\is_array($forms)) {
            trigger_deprecation('symfony/form', '5.3', 'Passing an array as the second argument of the "%s()" method is deprecated, pass "\Traversable" instead.', __METHOD__);
        }

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
    public function mapFormsToData(iterable $forms, &$data): void
    {
        if (\is_array($forms)) {
            trigger_deprecation('symfony/form', '5.3', 'Passing an array as the first argument of the "%s()" method is deprecated, pass "\Traversable" instead.', __METHOD__);
        }

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

    /**
     * @internal
     */
    public function getDataAccessor(): DataAccessorInterface
    {
        return $this->dataAccessor;
    }
}
