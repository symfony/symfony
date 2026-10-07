<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class IteratorConsumer
{
    public function __construct(
        #[AutowireIterator('app.loader')]
        private iterable $loaders,
    ) {
    }

    public function getLoaders(): iterable
    {
        return $this->loaders;
    }
}
