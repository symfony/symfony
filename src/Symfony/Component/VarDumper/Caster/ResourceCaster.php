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
 *
 * @final
 *
 * @internal
 */
class ResourceCaster
{
    public static function castDba(\Dba\Connection $dba, array $a, Stub $stub, bool $isNested): array
    {
        if (\PHP_VERSION_ID < 80402) {
            // @see https://github.com/php/php-src/issues/16990
            return $a;
        }

        $list = dba_list();
        $a['file'] = $list[(int) $dba];

        return $a;
    }

    public static function castProcess($process, array $a, Stub $stub, bool $isNested): array
    {
        return proc_get_status($process);
    }

    public static function castStream($stream, array $a, Stub $stub, bool $isNested): array
    {
        $a = stream_get_meta_data($stream) + static::castStreamContext($stream, $a, $stub, $isNested);
        if ($a['uri'] ?? false) {
            $a['uri'] = new LinkStub($a['uri']);
        }

        return $a;
    }

    public static function castStreamContext($stream, array $a, Stub $stub, bool $isNested): array
    {
        return @stream_context_get_params($stream) ?: $a;
    }
}
