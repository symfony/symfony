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

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ChainAccessor implements DataAccessorInterface
{
    private $accessors;

    /**
     * @param DataAccessorInterface[]|iterable $accessors
     */
    public function __construct(iterable $accessors)
    {
        $this->accessors = $accessors;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($data, FormInterface $form)
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isReadable($data, $form)) {
                return $accessor->getValue($data, $form);
            }
        }

        throw new AccessException('Unable to read from the given form data as no accessor in the chain is able to read the data.');
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(&$data, $value, FormInterface $form): void
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isWritable($data, $form)) {
                $accessor->setValue($data, $value, $form);

                return;
            }
        }

        throw new AccessException('Unable to write the given value as no accessor in the chain is able to set the data.');
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($data, FormInterface $form): bool
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isReadable($data, $form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($data, FormInterface $form): bool
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isWritable($data, $form)) {
                return true;
            }
        }

        return false;
    }
}
