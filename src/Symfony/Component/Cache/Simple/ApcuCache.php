<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Traits\ApcuTrait;
use Symfony\Contracts\Cache\CacheInterface;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3, use "%s" and type-hint for "%s" instead.', ApcuCache::class, ApcuAdapter::class, CacheInterface::class), E_USER_DEPRECATED);

/**
 * @deprecated since Symfony 4.3, use ApcuAdapter and type-hint for CacheInterface instead.
 */
class ApcuCache extends AbstractCache
{
    use ApcuTrait;

    public function __construct(string $namespace = '', int $defaultLifetime = 0, string $version = null)
    {
        $this->init($namespace, $defaultLifetime, $version);
    }
}
