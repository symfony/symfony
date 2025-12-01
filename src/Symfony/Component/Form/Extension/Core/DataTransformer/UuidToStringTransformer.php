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
 *
 * @implements DataTransformerInterface<Uuid, string>
 */
class UuidToStringTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Uuid) {
            throw new TransformationFailedException('Expected a Uuid.');
        }

        return (string) $value;
    }

    public function reverseTransform(mixed $value): ?Uuid
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if (!Uuid::isValid($value)) {
            throw new TransformationFailedException(\sprintf('The value "%s" is not a valid UUID.', $value));
        }

        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw new TransformationFailedException(\sprintf('The value "%s" is not a valid UUID.', $value), $e->getCode(), $e);
        }
    }
}
