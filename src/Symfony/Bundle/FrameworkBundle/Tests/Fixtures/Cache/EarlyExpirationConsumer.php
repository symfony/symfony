<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Cache;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class EarlyExpirationConsumer
{
    public function __construct(
        #[Target('cache.early_expiration')]
        public readonly TagAwareCacheInterface $cacheEarlyExpiration,
        public readonly EarlyExpirationComputation $computation,
    ) {
    }
}
