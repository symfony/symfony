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
 * Casts common resource types to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResourceCaster
{
    public static function castCurl($h, array $a, Stub $stub, $isNested)
    {
        return curl_getinfo($h);
    }

    public static function castDba($dba, array $a, Stub $stub, $isNested)
    {
        $list = dba_list();
        $a['file'] = $list[(int) $dba];

        return $a;
    }

    public static function castProcess($process, array $a, Stub $stub, $isNested)
    {
        return proc_get_status($process);
    }

    public static function castStream($stream, array $a, Stub $stub, $isNested)
    {
        return stream_get_meta_data($stream) + static::castStreamContext($stream, $a, $stub, $isNested);
    }

    public static function castStreamContext($stream, array $a, Stub $stub, $isNested)
    {
        return @stream_context_get_params($stream) ?: $a;
    }

    public static function castGd($gd, array $a, Stub $stub, $isNested)
    {
        $a['size'] = imagesx($gd).'x'.imagesy($gd);
        $a['trueColor'] = imageistruecolor($gd);

        return $a;
    }

    public static function castMysqlLink($h, array $a, Stub $stub, $isNested)
    {
        $a['host'] = mysql_get_host_info($h);
        $a['protocol'] = mysql_get_proto_info($h);
        $a['server'] = mysql_get_server_info($h);

        return $a;
    }
}
