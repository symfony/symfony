<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * Transforms between a UUID string and a Uuid object.
 *
 * @author Pavel Dyakonov <wapinet@mail.ru>
 */
class UuidToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforms a Uuid object into a string.
     *
     * @param Uuid $value A Uuid object
     *
     * @return string|null A value as produced by Uid component
     *
     * @throws TransformationFailedException If the given value is not a Uuid object
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Uuid) {
            throw new TransformationFailedException('Expected a Uuid.');
        }

        return (string) $value;
    }

    /**
     * Transforms a UUID string into a Uuid object.
     *
     * @param string $value A UUID string
     *
     * @return Uuid|null An instance of Uuid
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       or could not be transformed
     */
    public function reverseTransform($value)
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        try {
            $uuid = new Uuid($value);
        } catch (\InvalidArgumentException $e) {
            throw new TransformationFailedException(sprintf('The value "%s" is not a valid UUID.', $value), $e->getCode(), $e);
        }

        return $uuid;
    }
}
