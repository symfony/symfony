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
use Symfony\Component\Uid\Ulid;

/**
 * Transforms between a ULID string and a Ulid object.
 *
 * @author Pavel Dyakonov <wapinet@mail.ru>
 *
 * @implements DataTransformerInterface<Ulid, string>
 */
class UlidToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforms a Ulid object into a string.
     *
     * @param Ulid $value A Ulid object
     *
     * @throws TransformationFailedException If the given value is not a Ulid object
     */
    public function transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Ulid) {
            throw new TransformationFailedException('Expected a Ulid.');
        }

        return (string) $value;
    }

    /**
     * Transforms a ULID string into a Ulid object.
     *
     * @param string $value A ULID string
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       or could not be transformed
     */
    public function reverseTransform(mixed $value): ?Ulid
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        try {
            $ulid = new Ulid($value);
        } catch (\InvalidArgumentException $e) {
            throw new TransformationFailedException(sprintf('The value "%s" is not a valid ULID.', $value), $e->getCode(), $e);
        }

        return $ulid;
    }
}
