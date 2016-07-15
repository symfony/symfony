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
 * Casts Reflector related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ReflectionCaster
{
    private static $extraMap = array(
        'docComment' => 'getDocComment',
        'extension' => 'getExtensionName',
        'isDisabled' => 'isDisabled',
        'isDeprecated' => 'isDeprecated',
        'isInternal' => 'isInternal',
        'isUserDefined' => 'isUserDefined',
        'isGenerator' => 'isGenerator',
        'isVariadic' => 'isVariadic',
    );

    /**
     * @deprecated since Symfony 2.7, to be removed in 3.0.
     */
    public static function castReflector(\Reflector $c, array $a, Stub $stub, $isNested)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.7 and will be removed in 3.0.', E_USER_DEPRECATED);
        $a[Caster::PREFIX_VIRTUAL.'reflection'] = $c->__toString();

        return $a;
    }

    public static function castClosure(\Closure $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;
        $c = new \ReflectionFunction($c);

        $stub->class = 'Closure'; // HHVM generates unique class names for closures
        $a = static::castFunctionAbstract($c, $a, $stub, $isNested);

        if (isset($a[$prefix.'parameters'])) {
            foreach ($a[$prefix.'parameters'] as &$v) {
                $param = $v;
                $v = array();
                foreach (static::castParameter($param, array(), $stub, true) as $k => $param) {
                    if ("\0" === $k[0]) {
                        $v[substr($k, 3)] = $param;
                    }
                }
                unset($v['position'], $v['isVariadic'], $v['byReference'], $v);
            }
        }

        if ($f = $c->getFileName()) {
            $a[$prefix.'file'] = $f;
            $a[$prefix.'line'] = $c->getStartLine().' to '.$c->getEndLine();
        }

        $prefix = Caster::PREFIX_DYNAMIC;
        unset($a['name'], $a[$prefix.'this'], $a[$prefix.'parameter'], $a[Caster::PREFIX_VIRTUAL.'extra']);

        return $a;
    }

    public static function castClass(\ReflectionClass $c, array $a, Stub $stub, $isNested, $filter = 0)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        if ($n = \Reflection::getModifierNames($c->getModifiers())) {
            $a[$prefix.'modifiers'] = implode(' ', $n);
        }

        self::addMap($a, $c, array(
            'extends' => 'getParentClass',
            'implements' => 'getInterfaceNames',
            'constants' => 'getConstants',
        ));

        foreach ($c->getProperties() as $n) {
            $a[$prefix.'properties'][$n->name] = $n;
        }

        foreach ($c->getMethods() as $n) {
            $a[$prefix.'methods'][$n->name] = $n;
        }

        if (!($filter & Caster::EXCLUDE_VERBOSE) && !$isNested) {
            self::addExtra($a, $c);
        }

        return $a;
    }

    public static function castFunctionAbstract(\ReflectionFunctionAbstract $c, array $a, Stub $stub, $isNested, $filter = 0)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        self::addMap($a, $c, array(
            'returnsReference' => 'returnsReference',
            'returnType' => 'getReturnType',
            'class' => 'getClosureScopeClass',
            'this' => 'getClosureThis',
        ));

        if (isset($a[$prefix.'returnType'])) {
            $a[$prefix.'returnType'] = (string) $a[$prefix.'returnType'];
        }
        if (isset($a[$prefix.'this'])) {
            $a[$prefix.'this'] = new CutStub($a[$prefix.'this']);
        }

        foreach ($c->getParameters() as $v) {
            $k = '$'.$v->name;
            if ($v->isPassedByReference()) {
                $k = '&'.$k;
            }
            if (method_exists($v, 'isVariadic') && $v->isVariadic()) {
                $k = '...'.$k;
            }
            $a[$prefix.'parameters'][$k] = $v;
        }

        if ($v = $c->getStaticVariables()) {
            foreach ($v as $k => &$v) {
                $a[$prefix.'use']['$'.$k] = &$v;
            }
            unset($v);
        }

        if (!($filter & Caster::EXCLUDE_VERBOSE) && !$isNested) {
            self::addExtra($a, $c);
        }

        return $a;
    }

    public static function castMethod(\ReflectionMethod $c, array $a, Stub $stub, $isNested)
    {
        $a[Caster::PREFIX_VIRTUAL.'modifiers'] = implode(' ', \Reflection::getModifierNames($c->getModifiers()));

        return $a;
    }

    public static function castParameter(\ReflectionParameter $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        // Added by HHVM
        unset($a['info']);

        self::addMap($a, $c, array(
            'position' => 'getPosition',
            'isVariadic' => 'isVariadic',
            'byReference' => 'isPassedByReference',
        ));

        try {
            if (method_exists($c, 'hasType')) {
                if ($c->hasType()) {
                    $a[$prefix.'typeHint'] = $c->getType()->__toString();
                }
            } else {
                $v = explode(' ', $c->__toString(), 6);
                if (isset($v[5]) && 0 === strspn($v[4], '.&$')) {
                    $a[$prefix.'typeHint'] = $v[4];
                }
            }
        } catch (\ReflectionException $e) {
            if (preg_match('/^Class ([^ ]++) does not exist$/', $e->getMessage(), $m)) {
                $a[$prefix.'typeHint'] = $m[1];
            }
        }

        try {
            $a[$prefix.'default'] = $v = $c->getDefaultValue();
            if (method_exists($c, 'isDefaultValueConstant') && $c->isDefaultValueConstant()) {
                $a[$prefix.'default'] = new ConstStub($c->getDefaultValueConstantName(), $v);
            }
        } catch (\ReflectionException $e) {
            if (isset($a[$prefix.'typeHint']) && $c->allowsNull()) {
                $a[$prefix.'default'] = null;
            }
        }

        return $a;
    }

    public static function castProperty(\ReflectionProperty $c, array $a, Stub $stub, $isNested)
    {
        $a[Caster::PREFIX_VIRTUAL.'modifiers'] = implode(' ', \Reflection::getModifierNames($c->getModifiers()));
        self::addExtra($a, $c);

        return $a;
    }

    public static function castExtension(\ReflectionExtension $c, array $a, Stub $stub, $isNested)
    {
        self::addMap($a, $c, array(
            'version' => 'getVersion',
            'dependencies' => 'getDependencies',
            'iniEntries' => 'getIniEntries',
            'isPersistent' => 'isPersistent',
            'isTemporary' => 'isTemporary',
            'constants' => 'getConstants',
            'functions' => 'getFunctions',
            'classes' => 'getClasses',
        ));

        return $a;
    }

    public static function castZendExtension(\ReflectionZendExtension $c, array $a, Stub $stub, $isNested)
    {
        self::addMap($a, $c, array(
            'version' => 'getVersion',
            'author' => 'getAuthor',
            'copyright' => 'getCopyright',
            'url' => 'getURL',
        ));

        return $a;
    }

    private static function addExtra(&$a, \Reflector $c)
    {
        $a = &$a[Caster::PREFIX_VIRTUAL.'extra'];

        if (method_exists($c, 'getFileName') && $m = $c->getFileName()) {
            $a['file'] = $m;
            $a['line'] = $c->getStartLine().' to '.$c->getEndLine();
        }

        self::addMap($a, $c, self::$extraMap, '');
    }

    private static function addMap(&$a, \Reflector $c, $map, $prefix = Caster::PREFIX_VIRTUAL)
    {
        foreach ($map as $k => $m) {
            if (method_exists($c, $m) && false !== ($m = $c->$m()) && null !== $m) {
                $a[$prefix.$k] = $m instanceof \Reflector ? $m->name : $m;
            }
        }
    }
}
