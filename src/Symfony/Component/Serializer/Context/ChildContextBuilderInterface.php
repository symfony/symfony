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
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 *
 * @experimental in 4.3
 */
interface ChildContextBuilderInterface
{
    /**
     * Update the context for a sub level given a specific attribute.
     */
    public function createChildContextForAttribute(array $context, string $attribute): array;
}
