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

/**
 * @final since Symfony 4.4
 */
class SymfonyCaster
{
    private const REQUEST_GETTERS = [
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

        foreach (self::REQUEST_GETTERS as $prop => $getter) {
            $key = Caster::PREFIX_PROTECTED.$prop;
            if (\array_key_exists($key, $a) && null === $a[$key]) {
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
        if (isset($a[$multiKey])) {
            $a[$multiKey] = new CutStub($a[$multiKey]);
        }

        return $a;
    }

    public static function castHttpClientResponse($response, array $a, Stub $stub, $isNested)
    {
        $stub->cut += \count($a);
        $a = [];

        foreach ($response->getInfo() as $k => $v) {
            $a[Caster::PREFIX_VIRTUAL.$k] = $v;
        }

        return $a;
    }
}
