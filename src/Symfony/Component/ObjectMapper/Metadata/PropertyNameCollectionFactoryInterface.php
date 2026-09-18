<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

/**
 * Lists the non-static properties declared by a class and its parents.
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface PropertyNameCollectionFactoryInterface
{
    /**
     * @return list<string>
     */
    public function create(object $object): array;
}
