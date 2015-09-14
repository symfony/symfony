<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;

/**
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class ArgumentsFlattenExceptionProcessor implements FlattenExceptionProcessorInterface
{
    private $cloner;
    private $shareArguments;

    public function __construct(ClonerInterface $cloner = null, $shareArguments = true)
    {
        $this->cloner = $cloner;
        $this->shareArguments = $shareArguments;
    }

    /**
     * {@inheritdoc}
     */
    public function process(\Exception $exception, FlattenException $flattenException, $master)
    {
        if (!$master) {
            return;
        }

        $variables = array();
        $values = array();

        $e = $exception;
        $f = $flattenException;

        do {
            $trace = $f->getTrace();
            foreach ($e->getTrace() as $key => $entry) {
                if (!isset($entry['args']) || !isset($trace[$key])) {
                    continue;
                }

                $parameters = $this->getParameters($entry);

                $arguments = array();
                foreach ($entry['args'] as $position => $argument) {
                    $link = array_search($argument, $variables, true);

                    if (false === $link) {
                        $link = hash('md5', uniqid(mt_rand(), true), false);
                        $variables[$link] = $argument;
                        $values[$link] = $this->shareArguments ? array('link', $link) : $this->flatten($argument);
                    }

                    if (isset($parameters[$position])) {
                        $arguments[$parameters[$position]->getName()] = $values[$link];
                    } else {
                        $arguments[] = $values[$link];
                    }
                }

                $trace[$key]['args'] = $arguments;
            }
            $f->replaceTrace($trace);
        } while (($e = $e->getPrevious()) && ($f = $f->getPrevious()));

        if (!$this->shareArguments) {
            return;
        }

        $flattenVariables = $this->flatten($variables);
        foreach (array_merge(array($flattenException), $flattenException->getAllPrevious()) as $f) {
            $f->setExtra('trace_arguments', $flattenVariables);
        }
    }

    private function getParameters($entry)
    {
        if (!isset($entry['function'])) {
            return array();
        }

        try {
            if (isset($entry['class'])) {
                $ref = new \ReflectionMethod($entry['class'], $entry['function']);
            } else {
                $ref = new \ReflectionFunction($entry['function']);
            }
        } catch (\ReflectionException $e) {
            return array();
        }

        return $ref->getParameters();
    }

    private function flatten($variable)
    {
        if (null === $this->cloner) {
            return $this->flattenValue($variable);
        } else {
            return $this->cloner->cloneVar($variable);
        }
    }

    private function flattenValue($value, $level = 0, &$count = 0)
    {
        if ($count++ > 1e4) {
            return array('array', '*SKIPPED over 10000 entries*');
        }

        if (is_object($value)) {
            return array('object', get_class($value));
        }

        if (is_array($value)) {
            if ($level > 10) {
                return array('array', '*DEEP NESTED ARRAY*');
            }

            $array = array();
            foreach ($value as $k => $v) {
                $array[$k] = $this->flattenValue($v, $level + 1, $count);
            }

            return array('array', $array);
        }

        if (null === $value) {
            return array('null', null);
        }

        if (is_bool($value)) {
            return array('boolean', $value);
        }

        if (is_float($value) || is_int($value)) {
            return array('number', $value);
        }

        if (is_resource($value)) {
            return array('resource', get_resource_type($value));
        }

        if ($value instanceof \__PHP_Incomplete_Class) {
            // Special case of object, is_object will return false
            return array('incomplete-object', $this->getClassNameFromIncomplete($value));
        }

        return array('string', (string) $value);
    }

    private function getClassNameFromIncomplete(\__PHP_Incomplete_Class $value)
    {
        $array = new \ArrayObject($value);

        return $array['__PHP_Incomplete_Class_Name'];
    }
}
