<?php

namespace Symfony\Component\Debug\Utils;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;

class HtmlUtils
{
    /**
     * Formats an array as a string.
     *
     * @param array                 $args      The argument array
     * @param FlattenException|null $exception The flatten exception
     *
     * @return string
     */
    public function formatArgs(array $args, $exception = null)
    {
        $arguments = $exception instanceof FlattenException ? $exception->getExtra('trace_arguments') : null;

        $result = array();
        foreach ($args as $key => $item) {
            $formattedValue = $this->dumpArg($item, true, $arguments);
            $result[] = $formattedValue;
        }

        return implode(', ', $result);
    }

    public function dumpArg($arg, $compact = true, $arguments = null)
    {
        switch ($arg[0]) {
            case 'object':
                $parts = explode('\\', $arg[1]);
                $short = array_pop($parts);
                return sprintf('<abbr title="%s">%s</abbr>', $arg[1], $short);
            case 'array':
                if ($compact) {
                    return sprintf('<em>array:%d</em>', count((array) $arg[1]));
                }

                $result = array();
                foreach ((array) $arg[1] as $key => $value) {
                    $formattedValue = is_array($value) ? $this->dumpArg($value, $compact) : $value;
                    $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
                }

                return sprintf('[%s]', implode(', ', $result));
            case 'string':
                return sprintf("'%s'", htmlspecialchars((string) $arg[1], ENT_QUOTES));
            case 'number':
                return $arg[1];
            case 'null':
                return '<em>null</em>';
            case 'boolean':
                return '<em>'.strtolower(var_export($arg[1], true)).'</em>';
            case 'resource':
                return '<em>resource</em>';
            case 'link':
                if ($arguments instanceof Data && ($data = $arguments->getRawData()) && array_key_exists($arg[1], $data[1])) {
                    $value = $data[1][$arg[1]];
                    $type = gettype($value);

                    if (!$value instanceof Stub) {
                        return $this->dumpArg(array(strtolower($type), $value), $compact);
                    }

                    switch ($value->type) {
                        case Stub::TYPE_OBJECT:
                            return $this->dumpArg(array('object', $value->class), $compact);
                        case Stub::TYPE_STRING:
                            return $this->dumpArg(array('string', $value->value), $compact);
                        case Stub::TYPE_ARRAY:
                            return $this->dumpArg(array('array', $value->value), $compact);
                        case Stub::TYPE_RESOURCE:
                            return $this->dumpArg(array('resource', $value->value), $compact);
                        default:
                            return '<em>_</em>';
                    }
                } elseif (is_array($arguments) && isset($arguments[1][$arg[1]])) {
                    return $this->dumpArg($arguments[1][$arg[1]], $compact);
                }

                return '<em>_</em>';
            default:
                return str_replace("\n", '', var_export(htmlspecialchars((string) $arg[1], ENT_QUOTES), true));
        }
    }
}
