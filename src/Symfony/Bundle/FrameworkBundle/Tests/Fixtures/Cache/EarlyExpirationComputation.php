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

use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Cache\ItemInterface;

class EarlyExpirationComputation implements CallbackInterface
{
    public int $calls = 0;

    public function __invoke(CacheItemInterface $item, bool &$save): mixed
    {
        if ($item instanceof ItemInterface) {
            $item->tag('group');
        }
        $item->expiresAfter(3600);
        usleep(1000);

        return ++$this->calls;
    }
}
