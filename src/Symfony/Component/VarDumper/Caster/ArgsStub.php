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
 * Represents a list of function arguments.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ArgsStub extends EnumStub
{
    private static $parameters = array();

    public function __construct(array $args, $function, $class)
    {
        list($variadic, $params) = self::getParameters($function, $class);

        $values = array();
        foreach ($args as $k => $v) {
            $values[$k] = !is_scalar($v) && !$v instanceof Stub ? new CutStub($v) : $v;
        }
        if (null === $params) {
            parent::__construct($values, false);

            return;
        }
        if (count($values) < count($params)) {
            $params = array_slice($params, 0, count($values));
        } elseif (count($values) > count($params)) {
            $values[] = new EnumStub(array_splice($values, count($params)), false);
            $params[] = $variadic;
        }
        if (array('...') === $params) {
            $this->dumpKeys = false;
            $this->value = $values[0]->value;
        } else {
            $this->value = array_combine($params, $values);
        }
    }

    private static function getParameters($function, $class)
    {
        if (isset(self::$parameters[$k = $class.'::'.$function])) {
            return self::$parameters[$k];
        }

        try {
            $r = null !== $class ? new \ReflectionMethod($class, $function) : new \ReflectionFunction($function);
        } catch (\ReflectionException $e) {
            return array(null, null);
        }

        $variadic = '...';
        $params = array();
        foreach ($r->getParameters() as $v) {
            $k = '$'.$v->name;
            if ($v->isPassedByReference()) {
                $k = '&'.$k;
            }
            if (method_exists($v, 'isVariadic') && $v->isVariadic()) {
                $variadic .= $k;
            } else {
                $params[] = $k;
            }
        }

        return self::$parameters[$k] = array($variadic, $params);
    }
}
