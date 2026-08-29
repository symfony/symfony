<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

use Symfony\Component\VarExporter\Exception\LogicException;

/**
 * Code exported by versions < 8.1 calls this class. Throwing instead of failing with
 * a fatal error lets callers such as cache pools treat that code as a miss and regenerate it.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Hydrator
{
    public static function hydrate(): never
    {
        throw new LogicException('Code exported by symfony/var-exporter < 8.1 cannot be loaded anymore and must be regenerated.');
    }
}
