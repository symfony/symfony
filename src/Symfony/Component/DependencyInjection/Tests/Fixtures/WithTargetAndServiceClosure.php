<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\DependencyInjection\Attribute\Target;

class WithTargetAndServiceClosure
{
    public function __construct(
        #[Target('image.storage')]
        #[AutowireServiceClosure(BarInterface::class)]
        \Closure $closure,
        #[AutowireServiceClosure(BarInterface::class)]
        \Closure $imageStorage,
        #[Target('image.storage')]
        #[AutowireServiceClosure('bar')]
        \Closure $serviceId,
    ) {
    }
}
