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

use Symfony\Component\DependencyInjection\LazyProxy\GetterProxyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Cloner\Stub;

class SymfonyCaster
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

    public static function castGetterProxy(GetterProxyInterface $proxy, array $a, Stub $stub, $isNested)
    {
        $privatePrefix = sprintf("\0%s\0", $stub->class);
        $stub->class = get_parent_class($proxy).'@proxy';

        foreach ($a as $k => $v) {
            if ("\0" === $k[0] && 0 === strpos($k, $privatePrefix)) {
                ++$stub->cut;
                unset($a[$k]);
            }
        }

        return $a;
    }
}
