<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @method getSupportedTypes(?string $format): ?array
 */
interface NormalizerInterface
{
    /**
     * Normalizes an object into a set of arrays/scalars.
     *
     * @param mixed       $object  Object to normalize
     * @param string|null $format  Format the normalization result will be encoded as
     * @param array       $context Context options for the normalizer
     *
     * @return array|string|int|float|bool|\ArrayObject|null \ArrayObject is used to make sure an empty object is encoded as an object not an array
     *
     * @throws InvalidArgumentException   Occurs when the object given is not a supported type for the normalizer
     * @throws CircularReferenceException Occurs when the normalizer detects a circular reference when no circular
     *                                    reference handler can fix it
     * @throws LogicException             Occurs when the normalizer is not called in an expected context
     * @throws ExceptionInterface         Occurs for all the other cases of errors
     */
    public function normalize(mixed $object, string $format = null, array $context = []);

    /**
     * Checks whether the given class is supported for normalization by this normalizer.
     *
     * Since Symfony 6.3, this method will only be called if the $data type is
     * included in the supported types returned by getSupportedTypes().
     *
     * @see getSupportedTypes()
     *
     * @param mixed       $data    Data to normalize
     * @param string|null $format  The format being (de-)serialized from or into
     * @param array       $context Context options for the normalizer
     *
     * @return bool
     */
    public function supportsNormalization(mixed $data, string $format = null /* , array $context = [] */);

    /*
     * Return the types supported for normalization by this normalizer for this
     * format associated to a boolean value indicating if the result of
     * supports*() methods can be cached or if the result can not be cached
     * because it depends on the context.
     * Returning null means this normalizer will be considered for
     * every format/class.
     * Return an empty array if no type is supported for this format.
     *
     * @param string $format The format being (de-)serialized from or into
     *
     * @return array<class-string|string, bool>|null
     */
    /* public function getSupportedTypes(?string $format): ?array; */
}
