<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * MetadataAwareInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface MetadataAwareInterface
{
    /**
     * Gets meta data for given domain and key.
     *
     * @param string $domain The domain name
     * @param string $key    Key
     */
    public function getMetadata($key = '', $domain = 'messages');

    /**
     * Adds meta data to a message domain.
     *
     * @param string       $key    Key
     * @param string|array $value  Value
     * @param string       $domain The domain name
     */
    public function setMetadata($key, $value, $domain = 'messages');

    /**
     * Deletes meta data for given key and domain.
     *
     * @param string $domain The domain name
     * @param string $key    Key
     */
    public function deleteMetadata($key = '', $domain = 'messages');
}
