<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Contracts\Service\Attribute\TagInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Reset implements TagInterface
{
    public function __construct(
        private string $method
    ) {
    }

    public function getName(): string
    {
        return 'kernel.reset';
    }

    public function getAttributes(): array
    {
        return ['method' => $this->method];
    }
}
