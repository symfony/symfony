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

use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV5;

interface NameBasedUuidFactoryInterface
{
    public function create(string $name): UuidV5|UuidV3;
}
