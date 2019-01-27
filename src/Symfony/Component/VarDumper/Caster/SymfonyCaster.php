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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Cloner\Stub;

class SymfonyCaster
{
    private static $requestGetters = [
        'pathInfo' => 'getPathInfo',
        'requestUri' => 'getRequestUri',
        'baseUrl' => 'getBaseUrl',
        'basePath' => 'getBasePath',
        'method' => 'getMethod',
        'format' => 'getRequestFormat',
    ];

    public static function castRequest(Request $request, array $a, Stub $stub, $isNested)
    {
        $clone = null;

        foreach (self::$requestGetters as $prop => $getter) {
            if (null === $a[Caster::PREFIX_PROTECTED.$prop]) {
                if (null === $clone) {
                    $clone = clone $request;
                }
                $a[Caster::PREFIX_VIRTUAL.$prop] = $clone->{$getter}();
            }
        }

        return $a;
    }

    public static function castHttpClient($client, array $a, Stub $stub, $isNested)
    {
        $multiKey = sprintf("\0%s\0multi", \get_class($client));
        $a[$multiKey] = new CutStub($a[$multiKey]);

        return $a;
    }
}
