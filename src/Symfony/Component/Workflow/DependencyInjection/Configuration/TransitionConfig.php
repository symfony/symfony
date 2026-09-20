<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection\Configuration;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class TransitionConfig
{
    /**
     * @param list<ArcConfig>      $from
     * @param list<ArcConfig>      $to
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly array $from,
        public readonly array $to,
        public readonly ?string $guard = null,
        public readonly array $metadata = [],
    ) {
    }
}
