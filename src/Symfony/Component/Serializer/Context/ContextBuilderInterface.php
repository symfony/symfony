<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context;

/**
 * Common interface for context builders.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface ContextBuilderInterface
{
    /**
     * @param self|array<string, mixed> $context
     */
    public function withContext(self|array $context): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
