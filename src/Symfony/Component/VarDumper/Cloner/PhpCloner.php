<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PhpCloner extends AbstractCloner
{
    /**
     * {@inheritdoc}
     */
    protected function doClone($var)
    {
        $i = 0;                         // Current iteration position in $queue
        $len = 1;                       // Length of $queue
        $pos = 0;                       // Number of cloned items past the first level
        $refs = 0;                      // Number of hard+soft references in $var
        $queue = array(array($var));    // This breadth-first queue is the return value
        $arrayRefs = array();           // Map of queue indexes to stub array objects
        $hardRefs = array();            // By-ref map of stub objects' hashes to original hard `&` references
        $softRefs = array();            // Map of original object hashes to their stub object couterpart
        $values = array();              // Map of stub objects' hashes to original values
        $maxItems = $this->maxItems;
        $maxString = $this->maxString;
        $cookie = (object) array();     // Unique object used to detect hard references
        $isRef = false;
        $a = null;                      // Array cast for nested structures
        $stub = null;                   // Stub capturing the main properties of an original item value,
                                        // or null if the original value is used directly

        for ($i = 0; $i < $len; ++$i) {
            $indexed = true;            // Whether the currently iterated array is numerically indexed or not
            $j = -1;                    // Position in the currently iterated array
            $step = $queue[$i];         // Copy of the currently iterated array used for hard references detection
            foreach ($step as $k => $v) {
                // $k is the original key
                // $v is the original value or a stub object in case of hard references
                if ($indexed && $k !== ++$j) {
                    $indexed = false;
                }
                $step[$k] = $cookie;
                if ($queue[$i][$k] === $cookie) {
                    $queue[$i][$k] =& $stub;    // Break hard references to make $queue completely
                    unset($stub);               // independent from the original structure
                    if ($v instanceof Stub && isset($hardRefs[spl_object_hash($v)])) {
                        $v->ref = ++$refs;
                        $step[$k] = $queue[$i][$k] = $v;
                        continue;
                    }
                    $isRef = true;
                }
                // Create $stub when the original value $v can not be used directly
                // If $v is a nested structure, put that structure in array $a
                switch (gettype($v)) {
                    case 'string':
                        if (isset($v[0]) && !preg_match('//u', $v)) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_STRING;
                            $stub->class = Stub::STRING_BINARY;
                            if (0 <= $maxString && 0 < $cut = strlen($v) - $maxString) {
                                $stub->cut = $cut;
                                $cut = substr_replace($v, '', -$cut);
                            } else {
                                $cut = $v;
                            }
                            $stub->value = Data::utf8Encode($cut);
                        } elseif (0 <= $maxString && isset($v[1+($maxString>>2)]) && 0 < $cut = iconv_strlen($v, 'UTF-8') - $maxString) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_STRING;
                            $stub->class = Stub::STRING_UTF8;
                            $stub->cut = $cut;
                            $stub->value = iconv_substr($v, 0, $maxString, 'UTF-8');
                        }
                        break;

                    case 'integer':
                        break;

                    case 'array':
                        if ($v) {
                            $stub = $arrayRefs[$len] = new Stub();
                            $stub->type = Stub::TYPE_ARRAY;
                            $stub->class = Stub::ARRAY_ASSOC;
                            $stub->value = count($v);
                            $a = $v;
                        }
                        break;

                    case 'object':
                        if (empty($softRefs[$h = spl_object_hash($v)])) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_OBJECT;
                            $stub->class = get_class($v);
                            $stub->value = $v;
                            $a = $this->castObject($stub, 0 < $i);
                            if ($v !== $stub->value) {
                                if (Stub::TYPE_OBJECT !== $stub->type) {
                                    break;
                                }
                                $h = spl_object_hash($stub->value);
                            }
                            $stub->value = null;
                            if (0 <= $maxItems && $maxItems <= $pos) {
                                $stub->cut = count($a);
                                $a = null;
                            }
                        }
                        if (empty($softRefs[$h])) {
                            $softRefs[$h] = $stub;
                        } else {
                            $stub = $softRefs[$h];
                            $stub->ref = ++$refs;
                            $a = null;
                        }
                        break;

                    case 'resource':
                    case 'unknown type':
                        if (empty($softRefs[$h = (int) $v])) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_RESOURCE;
                            $stub->class = get_resource_type($v);
                            $stub->value = $v;
                            $a = $this->castResource($stub, 0 < $i);
                            if ($v !== $stub->value) {
                                if (Stub::TYPE_RESOURCE !== $stub->type) {
                                    break;
                                }
                                $h = (int) $stub->value;
                            }
                            $stub->value = null;
                            if (0 <= $maxItems && $maxItems <= $pos) {
                                $stub->cut = count($a);
                                $a = null;
                            }
                        }
                        if (empty($softRefs[$h])) {
                            $softRefs[$h] = $stub;
                        } else {
                            $stub = $softRefs[$h];
                            $stub->ref = ++$refs;
                            $a = null;
                        }
                        break;
                }

                if (isset($stub)) {
                    if ($isRef) {
                        if (Stub::TYPE_ARRAY === $stub->type) {
                            $step[$k] = $stub;
                        } else {
                            $step[$k] = new Stub();
                            $step[$k]->value = $stub;
                        }
                        $h = spl_object_hash($step[$k]);
                        $queue[$i][$k] = $hardRefs[$h] =& $step[$k];
                        $values[$h] = $v;
                        $isRef = false;
                    } else {
                        $queue[$i][$k] = $stub;
                    }

                    if ($a) {
                        if ($i && 0 <= $maxItems) {
                            $k = count($a);
                            if ($pos < $maxItems) {
                                if ($maxItems < $pos += $k) {
                                    $a = array_slice($a, 0, $maxItems - $pos);
                                    if ($stub->cut >= 0) {
                                        $stub->cut += $pos - $maxItems;
                                    }
                                }
                            } else {
                                if ($stub->cut >= 0) {
                                    $stub->cut += $k;
                                }
                                $stub = $a = null;
                                unset($arrayRefs[$len]);
                                continue;
                            }
                        }
                        $queue[$len] = $a;
                        $stub->position = $len++;
                    }
                    $stub = $a = null;
                } elseif ($isRef) {
                    $step[$k] = $queue[$i][$k] = new Stub();
                    $step[$k]->value = $v;
                    $h = spl_object_hash($step[$k]);
                    $hardRefs[$h] =& $step[$k];
                    $values[$h] = $v;
                    $isRef = false;
                }
            }

            if (isset($arrayRefs[$i])) {
                if ($indexed) {
                    $arrayRefs[$i]->class = Stub::ARRAY_INDEXED;
                }
                unset($arrayRefs[$i]);
            }
        }

        foreach ($values as $h => $v) {
            $hardRefs[$h] = $v;
        }

        return $queue;
    }
}
