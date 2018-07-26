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
 * Casts Redis class from ext-redis to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RedisCaster
{
    private static $serializer = array(
        \Redis::SERIALIZER_NONE => 'NONE',
        \Redis::SERIALIZER_PHP => 'PHP',
        2 => 'IGBINARY', // Optional Redis::SERIALIZER_IGBINARY
    );

    public static function castRedis(\Redis $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        if (\defined('HHVM_VERSION_ID')) {
            if (isset($a[Caster::PREFIX_PROTECTED.'serializer'])) {
                $ser = $a[Caster::PREFIX_PROTECTED.'serializer'];
                $a[Caster::PREFIX_PROTECTED.'serializer'] = isset(self::$serializer[$ser]) ? new ConstStub(self::$serializer[$ser], $ser) : $ser;
            }

            return $a;
        }

        if (!$connected = $c->isConnected()) {
            return $a + array(
                $prefix.'isConnected' => $connected,
            );
        }

        $ser = $c->getOption(\Redis::OPT_SERIALIZER);
        $retry = \defined('Redis::OPT_SCAN') ? $c->getOption(\Redis::OPT_SCAN) : 0;

        return $a + array(
            $prefix.'isConnected' => $connected,
            $prefix.'host' => $c->getHost(),
            $prefix.'port' => $c->getPort(),
            $prefix.'auth' => $c->getAuth(),
            $prefix.'dbNum' => $c->getDbNum(),
            $prefix.'timeout' => $c->getTimeout(),
            $prefix.'persistentId' => $c->getPersistentID(),
            $prefix.'options' => new EnumStub(array(
                'READ_TIMEOUT' => $c->getOption(\Redis::OPT_READ_TIMEOUT),
                'SERIALIZER' => isset(self::$serializer[$ser]) ? new ConstStub(self::$serializer[$ser], $ser) : $ser,
                'PREFIX' => $c->getOption(\Redis::OPT_PREFIX),
                'SCAN' => new ConstStub($retry ? 'RETRY' : 'NORETRY', $retry),
            )),
        );
    }

    public static function castRedisArray(\RedisArray $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return $a + array(
            $prefix.'hosts' => $c->_hosts(),
            $prefix.'function' => ClassStub::wrapCallable($c->_function()),
        );
    }
}
