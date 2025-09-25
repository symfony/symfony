<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Factory;

use Symfony\Component\Uid\Exception\LogicException;
use Symfony\Component\Uid\Uuid;

interface UuidFactoryInterface
{
    public function create(): Uuid;

    public function randomBased(): RandomBasedUuidFactoryInterface;

    public function timeBased(Uuid|string|null $node = null): TimeBasedUuidFactoryInterface;

    /**
     * @throws LogicException When no namespace is defined
     */
    public function nameBased(Uuid|string|null $namespace = null): NameBasedUuidFactoryInterface;
}
