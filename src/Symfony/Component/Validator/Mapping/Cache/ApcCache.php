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

@trigger_error('The '.__NAMESPACE__.'\ApcCache class is deprecated since Symfony 2.5 and will be removed in 3.0. Use DoctrineCache with the Doctrine\Common\Cache\ApcCache class instead.', E_USER_DEPRECATED);

use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @deprecated since version 2.5, to be removed in 3.0.
 *             Use DoctrineCache with \Doctrine\Common\Cache\ApcCache instead.
 */
class ApcCache implements CacheInterface
{
    private $prefix;

    public function __construct($prefix)
    {
        if (!extension_loaded('apc')) {
            throw new \RuntimeException('Unable to use ApcCache to cache validator mappings as APC is not enabled.');
        }

        $this->prefix = $prefix;
    }

    public function has($class)
    {
        if (!function_exists('apc_exists')) {
            $exists = false;

            apc_fetch($this->prefix.$class, $exists);

            return $exists;
        }

        return apc_exists($this->prefix.$class);
    }

    public function read($class)
    {
        return apc_fetch($this->prefix.$class);
    }

    public function write(ClassMetadata $metadata)
    {
        apc_store($this->prefix.$metadata->getClassName(), $metadata);
    }
}
