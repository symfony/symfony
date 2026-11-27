<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * Calls the services tagged with "kernel.worker_warmer" once a worker booted its kernel.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class WorkerWarmer
{
    /**
     * @param \Traversable<string, object> $warmers
     * @param array<string, list<string>>  $methods
     */
    public function __construct(
        private \Traversable $warmers,
        private array $methods,
    ) {
    }

    public function warmUp(): void
    {
        foreach ($this->warmers as $id => $warmer) {
            foreach ($this->methods[$id] as $method) {
                $warmer->$method();
            }
        }
    }
}
