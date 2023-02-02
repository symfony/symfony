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

use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @method getSupportedTypes(?string $format): ?array
 */
interface DenormalizerInterface
{
    public const COLLECT_DENORMALIZATION_ERRORS = 'collect_denormalization_errors';

    /**
     * Denormalizes data back into an object of the given class.
     *
     * @param mixed       $data    Data to restore
     * @param string      $type    The expected class to instantiate
     * @param string|null $format  Format the given data was extracted from
     * @param array       $context Options available to the denormalizer
     *
     * @return mixed
     *
     * @throws BadMethodCallException   Occurs when the normalizer is not called in an expected context
     * @throws InvalidArgumentException Occurs when the arguments are not coherent or not supported
     * @throws UnexpectedValueException Occurs when the item cannot be hydrated with the given data
     * @throws ExtraAttributesException Occurs when the item doesn't have attribute to receive given data
     * @throws LogicException           Occurs when the normalizer is not supposed to denormalize
     * @throws RuntimeException         Occurs if the class cannot be instantiated
     * @throws ExceptionInterface       Occurs for all the other cases of errors
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []);

    /**
     * Checks whether the given class is supported for denormalization by this normalizer.
     *
     * Since Symfony 6.3, this method will only be called if the type is
     * included in the supported types returned by getSupportedTypes().
     *
     * @see getSupportedTypes()
     *
     * @param mixed       $data    Data to denormalize from
     * @param string      $type    The class to which the data should be denormalized
     * @param string|null $format  The format being deserialized from
     * @param array       $context Options available to the denormalizer
     *
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null /* , array $context = [] */);

    /*
     * Return the types supported for normalization by this denormalizer for
     * this format associated to a boolean value indicating if the result of
     * supports*() methods can be cached or if the result can not be cached
     * because it depends on the context.
     * Returning null means this denormalizer will be considered for
     * every format/class.
     * Return an empty array if no type is supported for this format.
     *
     * @param string $format The format being (de-)serialized from or into
     *
     * @return array<class-string|string, bool>|null
     */
    /* public function getSupportedTypes(?string $format): ?array; */
}
