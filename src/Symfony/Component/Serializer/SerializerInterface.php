<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SerializerInterface
{
    const RETURN_RESULT = 'return_result';

    /**
     * Serializes data in the appropriate format.
     *
     * When context option `return_result` is enabled, the serializer must
     * always return an instance of
     * {@see \Symfony\Component\Serializer\Result\NormalizationResult}.
     *
     * @param mixed  $data    Any data
     * @param string $format  Format name
     * @param array  $context Options normalizers/encoders have access to
     *
     * @return string
     */
    public function serialize($data, string $format, array $context = []);

    /**
     * Deserializes data into the given type.
     *
     * When context option `return_result` is enabled, the serializer must
     * always return an instance of
     * {@see \Symfony\Component\Serializer\Result\DenormalizationResult}.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public function deserialize($data, string $type, string $format, array $context = []);
}
