<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Fixtures;

use Symfony\Component\AssetMapper\Event\PreAssetsCompileEvent;

class PreAssetsCompileListener
{
    /** @var callable|null */
    public $listener;

    public function __invoke(PreAssetsCompileEvent $event): void
    {
        ($this->listener ?? static fn () => null)($event);
    }
}
