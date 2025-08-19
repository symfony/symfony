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

use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Uid\UuidV8;

/**
 * Interface for UUID factories that can generate UUIDs of different versions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UuidFactoryInterface
{
    public function v1(): UuidV1;

    public function v3(): UuidV3;

    public function v4(): UuidV4;

    public function v5(): UuidV5;

    public function v6(): UuidV6;

    public function v7(): UuidV7;

    public function v8(): UuidV8;
}