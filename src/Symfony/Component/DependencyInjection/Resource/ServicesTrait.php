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
     *     class?: string,
     *     alias?: string,
     *     parent?: string,
     *     shared?: bool,
     *     synthetic?: bool,
     *     lazy?: bool|string,
     *     public?: bool,
     *     abstract?: bool,
     *     deprecated?: string|array{package: string, version: string, message?: string},
     *     factory?: string|array{0: string|null, 1: string},
     *     constructor?: string,
     *     file?: string,
     *     arguments?: array,
     *     properties?: array<string, mixed>,
     *     configurator?: string|array{0: string|null, 1: string},
     *     calls?: array<array{0: string, 1?: array, 2?: bool}|array{method: string, arguments?: array, returns_clone?: bool}>,
     *     tags?: array<array{name: string, ...}|string>,
     *     resource_tags?: array<array{name: string, ...}|string>,
     *     decorates?: string,
     *     decoration_inner_name?: string|null,
     *     decoration_priority?: int,
     *     decoration_on_invalid?: "exception"|"ignore"|null,
     *     autowire?: bool,
     *     autoconfigure?: bool,
     *     bind?: array<string, mixed>,
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
