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
 * Writes and reads values to/from an object or array using callback functions.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class CallbackAccessor implements DataAccessorInterface
{
    public function getValue(object|array $data, FormInterface $form): mixed
    {
        if (null === $getter = $form->getConfig()->getOption('getter')) {
            throw new AccessException('Unable to read from the given form data as no getter is defined.');
        }

        return ($getter)($data, $form);
    }

    public function setValue(object|array &$data, mixed $value, FormInterface $form): void
    {
        if (null === $setter = $form->getConfig()->getOption('setter')) {
            throw new AccessException('Unable to write the given value as no setter is defined.');
        }

        ($setter)($data, $form->getData(), $form);
    }

    public function isReadable(object|array $data, FormInterface $form): bool
    {
        return null !== $form->getConfig()->getOption('getter');
    }

    public function isWritable(object|array $data, FormInterface $form): bool
    {
        return null !== $form->getConfig()->getOption('setter');
    }
}
