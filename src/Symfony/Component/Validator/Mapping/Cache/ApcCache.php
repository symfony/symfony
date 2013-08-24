<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Cache;

use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @since v2.0.0
 */
class ApcCache implements CacheInterface
{
    private $prefix;

    /**
     * @since v2.0.0
     */
    public function __construct($prefix)
    {
        if (!extension_loaded('apc')) {
            throw new \RuntimeException('Unable to use ApcCache to cache validator mappings as APC is not enabled.');
        }

        $this->prefix = $prefix;
    }

    /**
     * @since v2.0.0
     */
    public function has($class)
    {
        if (!function_exists('apc_exists')) {
            $exists = false;

            apc_fetch($this->prefix.$class, $exists);

            return $exists;
        }

        return apc_exists($this->prefix.$class);
    }

    /**
     * @since v2.0.0
     */
    public function read($class)
    {
        return apc_fetch($this->prefix.$class);
    }

    /**
     * @since v2.0.0
     */
    public function write(ClassMetadata $metadata)
    {
        apc_store($this->prefix.$metadata->getClassName(), $metadata);
    }
}
