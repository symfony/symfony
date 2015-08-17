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

/**
 * Defines the interface of post-normalizers.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
interface PostNormalizerInterface
{
    /**
     * Normalizes an object into a set of arrays/scalars.
     *
     * @param mixed  $originalData Initial data to normalize
     * @param array  $data         Normalized data to post-normalize
     * @param string $format       format the normalization result will be encoded as
     * @param array  $context      Context options for the normalizer
     *
     * @return array|bool|float|int|null|string
     */
    public function postNormalize($originalData, $data, $format = null, array $context = array());

    /**
     * Checks whether the given class is supported for post-normalization by this normalizer.
     *
     * @param mixed  $originalData Initial data to normalize
     * @param array  $data         Normalized data to post-normalize.
     * @param string $format       The format being (de-)serialized from or into.
     *
     * @return bool
     */
    public function supportsPostNormalization($originalData, $data, $format = null);
}
