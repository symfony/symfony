<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class IntlCaster
{
    public static function castMessageFormatter(\MessageFormatter $c, array $a, Stub $stub, $isNested)
    {
        $a += array(
            Caster::PREFIX_VIRTUAL.'locale' => $c->getLocale(),
            Caster::PREFIX_VIRTUAL.'pattern' => $c->getPattern(),
        );

        if ($errorCode = $c->getErrorCode()) {
            $a += array(
                Caster::PREFIX_VIRTUAL.'error_code' => $errorCode,
                Caster::PREFIX_VIRTUAL.'error_message' => $c->getErrorMessage(),
            );
        }

        return $a;
    }
}
