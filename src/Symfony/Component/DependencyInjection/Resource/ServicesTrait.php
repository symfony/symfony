<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Resource;

trait ServicesTrait
{
    private array $services = [];

    /**
     * @var array<array{
     *     id: string,
     *     class?: string|int|float|bool,
     * }>|array<string, class-string|null> $services
     */
    public function services(array $services): static
    {
        $this->services = $services;

        return $this;
    }

    public function getServices(): array
    {
        return $this->services;
    }
}
