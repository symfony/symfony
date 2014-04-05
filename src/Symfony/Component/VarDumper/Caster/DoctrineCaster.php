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

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Proxy\Proxy as CommonProxy;
use Doctrine\ORM\Proxy\Proxy as OrmProxy;
use Doctrine\ORM\PersistentCollection;

/**
 * Casts Doctrine related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DoctrineCaster
{
    public static function castCommonProxy(CommonProxy $proxy, array $a, $isNested, &$cut)
    {
        unset(
            $a['__cloner__'],
            $a['__initializer__']
        );
        $cut += 2;

        return $a;
    }

    public static function castOrmProxy(OrmProxy $proxy, array $a, $isNested, &$cut)
    {
        $prefix = "\0Doctrine\\ORM\\Proxy\\Proxy\0";
        unset(
            $a[$prefix.'_entityPersister'],
            $a[$prefix.'_identifier']
        );
        $cut += 2;

        return $a;
    }

    public static function castObjectManager(ObjectManager $manager, array $a, $isNested, &$cut)
    {
        if ($isNested) {
            $cut += count($a);

            return array();
        }

        return $a;
    }

    public static function castPersistentCollection(PersistentCollection $coll, array $a, $isNested, &$cut)
    {
        $prefix = "\0Doctrine\\ORM\\PersistentCollection\0";
        unset(
            $a[$prefix.'snapshot'],
            $a[$prefix.'association'],
            $a[$prefix.'em'],
            $a[$prefix.'typeClass']
        );
        $cut += 4;

        return $a;
    }
}
