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
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

class UidToStringTransformer implements DataTransformerInterface
{
    /**
     * @param AbstractUid $uid An \AbstractUid object
     *
     * @return string|null A string representation of UUID or a ULID
     *
     * @throws TransformationFailedException If the given value is not a \AbstractUid
     */
    public function transform($uid)
    {
        if (null === $uid) {
            return '';
        }

        if (!$uid instanceof AbstractUid) {
            throw new TransformationFailedException('Expected an \AbstractUid.');
        }

        return (string) $uid;
    }

    /**
     * @param string $value A string representation of UUID or a ULID
     *
     * @return AbstractUid|null An instance of AbstractUid
     *
     * @throws TransformationFailedException If the given value is not a string, or could not be transformed
     */
    public function reverseTransform($value)
    {
        if (empty($value)) {
            return null;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if (Uuid::isValid($value)) {
            return Uuid::fromString($value);
        }

        if (Ulid::isValid($value)) {
            return Ulid::fromString($value);
        }

        throw new TransformationFailedException('This value is not a valid string representation of a UUID or ULID.');
    }
}
