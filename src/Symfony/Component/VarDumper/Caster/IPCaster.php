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

use Darsyn\IP\IP;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts IP class (darsyn/ip) to array representation.
 *
 * @author Patrick Landolt <patrick.landolt@artack.ch>
 */
class IPCaster
{
    public static function castIP(IP $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return array(
            $prefix.'version' => $c->getVersion(),
            $prefix.'short' => $c->getShortAddress(),
            $prefix.'long' => $c->getLongAddress(),
            $prefix.'mapped' => $c->isMapped(),
            $prefix.'derived' => $c->isDerived(),
            $prefix.'compatible' => $c->isCompatible(),
            $prefix.'embedded' => $c->isEmbedded(),
            $prefix.'link local' => $c->isLinkLocal(),
            $prefix.'loopback' => $c->isLoopback(),
            $prefix.'multicast' => $c->isMulticast(),
            $prefix.'private use' => $c->isPrivateUse(),
            $prefix.'unspecified' => $c->isUnspecified(),
        );
    }
}
