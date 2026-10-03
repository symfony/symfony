<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection;

use Symfony\Component\Workflow\Arc;

/**
 * Describes a transition of a workflow to register in the container.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class TransitionDescriptor
{
    /**
     * @param list<Arc>            $from
     * @param list<Arc>            $to
     * @param string|null          $guard    An expression that must be true to enable the transition
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
