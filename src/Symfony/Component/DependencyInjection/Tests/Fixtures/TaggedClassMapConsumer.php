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

use Symfony\Component\DependencyInjection\Attribute\AutowireClassMap;

final class TaggedClassMapConsumer
{
    public function __construct(
        #[AutowireClassMap('foo_bar', indexAttribute: 'foo')]
        private array $param,
    ) {
    }

    public function getParam(): array
    {
        return $this->param;
    }
}
