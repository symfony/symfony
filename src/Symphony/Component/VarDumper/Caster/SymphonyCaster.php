<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Caster;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\VarDumper\Cloner\Stub;

class SymphonyCaster
{
    private static $requestGetters = array(
        'pathInfo' => 'getPathInfo',
        'requestUri' => 'getRequestUri',
        'baseUrl' => 'getBaseUrl',
        'basePath' => 'getBasePath',
        'method' => 'getMethod',
        'format' => 'getRequestFormat',
    );

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
}
