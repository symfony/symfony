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
 * Defines the interface to create a child context during serialization/deserialization or instantiation process.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
interface ChildContextFactoryInterface
{
    public function create(array $parentContext, string $attribute, ?string $format = null, array $defaultContext = []): array;
}
