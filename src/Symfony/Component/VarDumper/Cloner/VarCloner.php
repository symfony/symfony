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
class VarCloner extends AbstractCloner
{
    private static $hashMask = 0;
    private static $hashOffset = 0;

    /**
     * {@inheritdoc}
     */
    protected function doClone($var)
    {
        $useExt = $this->useExt;
        $i = 0;                         // Current iteration position in $queue
        $len = 1;                       // Length of $queue
        $pos = 0;                       // Number of cloned items past the first level
        $refs = 0;                      // Hard references counter
        $queue = array(array($var));    // This breadth-first queue is the return value
        $arrayRefs = array();           // Map of queue indexes to stub array objects
        $hardRefs = array();            // Map of original zval hashes to stub objects
        $objRefs = array();             // Map of original object handles to their stub object couterpart
        $resRefs = array();             // Map of original resource handles to their stub object couterpart
        $values = array();              // Map of stub objects' hashes to original values
        $maxItems = $this->maxItems;
        $maxString = $this->maxString;
        $cookie = (object) array();     // Unique object used to detect hard references
        $gid = uniqid(mt_rand(), true); // Unique string used to detect the special $GLOBALS variable
        $a = null;                      // Array cast for nested structures
        $stub = null;                   // Stub capturing the main properties of an original item value
                                        // or null if the original value is used directly
        $zval = array(                  // Main properties of the current value
            'type' => null,
            'zval_isref' => null,
            'zval_hash' => null,
            'array_count' => null,
            'object_class' => null,
            'object_handle' => null,
            'resource_type' => null,
        );
        if (!self::$hashMask) {
            self::initHashMask();
        }
        $hashMask = self::$hashMask;
        $hashOffset = self::$hashOffset;

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
                if ($useExt) {
                    $zval = symfony_zval_info($k, $step);
                } else {
                    $step[$k] = $cookie;
                    if ($zval['zval_isref'] = $queue[$i][$k] === $cookie) {
                        $zval['zval_hash'] = $v instanceof Stub ? spl_object_hash($v) : null;
                    }
                    $zval['type'] = gettype($v);
                }
                if ($zval['zval_isref']) {
                    $queue[$i][$k] = &$stub;    // Break hard references to make $queue completely
                    unset($stub);               // independent from the original structure
                    if (isset($hardRefs[$zval['zval_hash']])) {
                        $queue[$i][$k] = $useExt ? ($v = $hardRefs[$zval['zval_hash']]) : ($step[$k] = $v);
                        if ($v->value instanceof Stub && (Stub::TYPE_OBJECT === $v->value->type || Stub::TYPE_RESOURCE === $v->value->type)) {
                            ++$v->value->refCount;
                        }
                        ++$v->refCount;
                        continue;
                    }
                }
                // Create $stub when the original value $v can not be used directly
                // If $v is a nested structure, put that structure in array $a
                switch ($zval['type']) {
                    case 'string':
                        if (isset($v[0]) && !preg_match('//u', $v)) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_STRING;
                            $stub->class = Stub::STRING_BINARY;
                            if (0 <= $maxString && 0 < $cut = strlen($v) - $maxString) {
                                $stub->cut = $cut;
                                $stub->value = substr($v, 0, -$cut);
                            } else {
                                $stub->value = $v;
                            }
                        } elseif (0 <= $maxString && isset($v[1 + ($maxString >> 2)]) && 0 < $cut = iconv_strlen($v, 'UTF-8') - $maxString) {
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

                            // Copies of $GLOBALS have very strange behavior,
                            // let's detect them with some black magic
                            $a = $v;
                            $a[$gid] = true;

                            // Happens with copies of $GLOBALS
                            if (isset($v[$gid])) {
                                unset($v[$gid]);
                                $a = array();
                                foreach ($v as $gk => &$gv) {
                                    $a[$gk] = &$gv;
                                }
                            } else {
                                $a = $v;
                            }

                            $stub->value = $zval['array_count'] ?: count($a);
                        }
                        break;

                    case 'object':
                        if (empty($objRefs[$h = $zval['object_handle'] ?: ($hashMask ^ hexdec(substr(spl_object_hash($v), $hashOffset, PHP_INT_SIZE)))])) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_OBJECT;
                            $stub->class = $zval['object_class'] ?: get_class($v);
                            $stub->value = $v;
                            $stub->handle = $h;
                            $a = $this->castObject($stub, 0 < $i);
                            if ($v !== $stub->value) {
                                if (Stub::TYPE_OBJECT !== $stub->type) {
                                    break;
                                }
                                if ($useExt) {
                                    $zval['type'] = $stub->value;
                                    $zval = symfony_zval_info('type', $zval);
                                    $h = $zval['object_handle'];
                                } else {
                                    $h = $hashMask ^ hexdec(substr(spl_object_hash($stub->value), $hashOffset, PHP_INT_SIZE));
                                }
                                $stub->handle = $h;
                            }
                            $stub->value = null;
                            if (0 <= $maxItems && $maxItems <= $pos) {
                                $stub->cut = count($a);
                                $a = null;
                            }
                        }
                        if (empty($objRefs[$h])) {
                            $objRefs[$h] = $stub;
                        } else {
                            $stub = $objRefs[$h];
                            ++$stub->refCount;
                            $a = null;
                        }
                        break;

                    case 'resource':
                    case 'unknown type':
                        if (empty($resRefs[$h = (int) $v])) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_RESOURCE;
                            $stub->class = $zval['resource_type'] ?: get_resource_type($v);
                            $stub->value = $v;
                            $stub->handle = $h;
                            $a = $this->castResource($stub, 0 < $i);
                            $stub->value = null;
                            if (0 <= $maxItems && $maxItems <= $pos) {
                                $stub->cut = count($a);
                                $a = null;
                            }
                        }
                        if (empty($resRefs[$h])) {
                            $resRefs[$h] = $stub;
                        } else {
                            $stub = $resRefs[$h];
                            ++$stub->refCount;
                            $a = null;
                        }
                        break;
                }

                if (isset($stub)) {
                    if ($zval['zval_isref']) {
                        if ($useExt) {
                            $queue[$i][$k] = $hardRefs[$zval['zval_hash']] = $v = new Stub();
                            $v->value = $stub;
                        } else {
                            $step[$k] = new Stub();
                            $step[$k]->value = $stub;
                            $h = spl_object_hash($step[$k]);
                            $queue[$i][$k] = $hardRefs[$h] = &$step[$k];
                            $values[$h] = $v;
                        }
                        $queue[$i][$k]->handle = ++$refs;
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
                } elseif ($zval['zval_isref']) {
                    if ($useExt) {
                        $queue[$i][$k] = $hardRefs[$zval['zval_hash']] = new Stub();
                        $queue[$i][$k]->value = $v;
                    } else {
                        $step[$k] = $queue[$i][$k] = new Stub();
                        $step[$k]->value = $v;
                        $h = spl_object_hash($step[$k]);
                        $hardRefs[$h] = &$step[$k];
                        $values[$h] = $v;
                    }
                    $queue[$i][$k]->handle = ++$refs;
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

    private static function initHashMask()
    {
        $obj = (object) array();
        self::$hashOffset = 16 - PHP_INT_SIZE;
        self::$hashMask = -1;

        if (defined('HHVM_VERSION')) {
            self::$hashOffset += 16;
        } else {
            // check if we are nested in an output buffering handler to prevent a fatal error with ob_start() below
            $obFuncs = array('ob_clean', 'ob_end_clean', 'ob_flush', 'ob_end_flush', 'ob_get_contents', 'ob_get_flush');
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                if (isset($frame['function'][0]) && !isset($frame['class']) && 'o' === $frame['function'][0] && in_array($frame['function'], $obFuncs)) {
                    $frame['line'] = 0;
                    break;
                }
            }
            if (!empty($frame['line'])) {
                ob_start();
                debug_zval_dump($obj);
                self::$hashMask = substr(ob_get_clean(), 17);
            }
        }

        self::$hashMask ^= hexdec(substr(spl_object_hash($obj), self::$hashOffset, PHP_INT_SIZE));
    }
}
