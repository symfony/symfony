<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost;

use Symfony\Component\VarExporter\LazyGhostTrait;
use Symfony\Component\VarExporter\LazyObjectInterface;

class ChildMagicClass extends MagicClass implements LazyObjectInterface
{
    use LazyGhostTrait;

    private const LAZY_OBJECT_PROPERTY_SCOPES = [
        "\0".self::class."\0".'data' => [self::class, 'data', null],
        "\0".self::class."\0".'lazyObjectState' => [self::class, 'lazyObjectState', null],
        "\0".parent::class."\0".'data' => [parent::class, 'data', null],
        'cloneCounter' => [self::class, 'cloneCounter', null],
        'data' => [self::class, 'data', null],
        'lazyObjectState' => [self::class, 'lazyObjectState', null],
    ];

    private int $data = 123;
}
