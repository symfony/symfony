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
        $stub = null;                   // stdClass capturing the main properties of an original item value,
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
                    if ($v instanceof \stdClass && isset($hardRefs[spl_object_hash($v)])) {
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
                            if (0 <= $maxString && 0 < $cut = strlen($v) - $maxString) {
                                $stub = substr_replace($v, '', -$cut);
                                $stub = (object) array('cut' => $cut, 'bin' => Data::utf8Encode($stub));
                            } else {
                                $stub = (object) array('bin' => Data::utf8Encode($v));
                            }
                        } elseif (0 <= $maxString && isset($v[1+($maxString>>2)]) && 0 < $cut = iconv_strlen($v, 'UTF-8') - $maxString) {
                            $stub = iconv_substr($v, 0, $maxString, 'UTF-8');
                            $stub = (object) array('cut' => $cut, 'str' => $stub);
                        }
                        break;

                    case 'integer':
                        break;

                    case 'array':
                        if ($v) {
                            $stub = (object) array('count' => count($v));
                            $arrayRefs[$len] = $stub;
                            $a = $v;
                        }
                        break;

                    case 'object':
                        if (empty($softRefs[$h = spl_object_hash($v)])) {
                            $stub = $softRefs[$h] = (object) array('class' => get_class($v));
                            if (0 > $maxItems || $pos < $maxItems) {
                                $a = $this->castObject($stub->class, $v, 0 < $i, $cut);
                                if ($cut) {
                                    $stub->cut = $cut;
                                }
                            } else {
                                $stub->cut = -1;
                            }
                        } else {
                            $stub = $softRefs[$h];
                            $stub->ref = ++$refs;
                        }
                        break;

                    case 'resource':
                    case 'unknown type':
                        if (empty($softRefs[$h = (int) substr_replace($v, '', 0, 13)])) {
                            $stub = $softRefs[$h] = (object) array('res' => @get_resource_type($v));
                            if (0 > $maxItems || $pos < $maxItems) {
                                $a = $this->castResource($stub->res, $v, 0 < $i);
                            } else {
                                $stub->cut = -1;
                            }
                        } else {
                            $stub = $softRefs[$h];
                            $stub->ref = ++$refs;
                        }
                        break;
                }

                if (isset($stub)) {
                    if ($isRef) {
                        if (isset($stub->count)) {
                            $step[$k] = $stub;
                        } else {
                            $step[$k] = (object) array('val' => $stub);
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
                                    if (empty($stub->cut)) {
                                        $stub->cut = $pos - $maxItems;
                                    } elseif ($stub->cut > 0) {
                                        $stub->cut += $pos - $maxItems;
                                    }
                                }
                            } else {
                                if (empty($stub->cut)) {
                                    $stub->cut = $k;
                                } elseif ($stub->cut > 0) {
                                    $stub->cut += $k;
                                }
                                $stub = $a = null;
                                unset($arrayRefs[$len]);
                                continue;
                            }
                        }
                        $queue[$len] = $a;
                        $stub->pos = $len++;
                    }
                    $stub = $a = null;
                } elseif ($isRef) {
                    $step[$k] = $queue[$i][$k] = $v = (object) array('val' => $v);
                    $h = spl_object_hash($v);
                    $hardRefs[$h] =& $step[$k];
                    $values[$h] = $v->val;
                    $isRef = false;
                }
            }

            if (isset($arrayRefs[$i])) {
                if ($indexed) {
                    $arrayRefs[$i]->indexed = 1;
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
