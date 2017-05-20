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

use Symfony\Component\VarDumper\Caster\Caster;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Data implements \ArrayAccess, \Countable, \IteratorAggregate
{
    private $data;
    private $position = 0;
    private $key = 0;
    private $maxDepth = 20;
    private $maxItemsPerDepth = -1;
    private $useRefHandles = -1;

    /**
     * @param array $data A array as returned by ClonerInterface::cloneVar()
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return string The type of the value.
     */
    public function getType()
    {
        $item = $this->data[$this->position][$this->key];

        if ($item instanceof Stub && Stub::TYPE_REF === $item->type && !$item->position) {
            $item = $item->value;
        }
        if (!$item instanceof Stub) {
            return gettype($item);
        }
        if (Stub::TYPE_STRING === $item->type) {
            return 'string';
        }
        if (Stub::TYPE_ARRAY === $item->type) {
            return 'array';
        }
        if (Stub::TYPE_OBJECT === $item->type) {
            return $item->class;
        }
        if (Stub::TYPE_RESOURCE === $item->type) {
            return $item->class.' resource';
        }
    }

    /**
     * @param bool $recursive Whether values should be resolved recursively or not.
     *
     * @return scalar|array|null|Data[] A native representation of the original value.
     */
    public function getValue($recursive = false)
    {
        $item = $this->data[$this->position][$this->key];

        if ($item instanceof Stub && Stub::TYPE_REF === $item->type && !$item->position) {
            $item = $item->value;
        }
        if (!$item instanceof Stub) {
            return $item;
        }
        if (Stub::TYPE_STRING === $item->type) {
            return $item->value;
        }

        $children = $item->position ? $this->data[$item->position] : array();

        foreach ($children as $k => $v) {
            if ($recursive && !$v instanceof Stub) {
                continue;
            }
            $children[$k] = clone $this;
            $children[$k]->key = $k;
            $children[$k]->position = $item->position;

            if ($recursive) {
                if ($v instanceof Stub && Stub::TYPE_REF === $v->type && $v->value instanceof Stub) {
                    $recursive = (array) $recursive;
                    if (isset($recursive[$v->value->position])) {
                        continue;
                    }
                    $recursive[$v->value->position] = true;
                }
                $children[$k] = $children[$k]->getValue($recursive);
            }
        }

        return $children;
    }

    public function count()
    {
        return count($this->getValue());
    }

    public function getIterator()
    {
        if (!is_array($value = $this->getValue())) {
            throw new \LogicException(sprintf('%s object holds non-iterable type "%s".', self::class, gettype($value)));
        }

        foreach ($value as $k => $v) {
            yield $k => $v;
        }
    }

    public function __get($key)
    {
        if (null !== $data = $this->seek($key)) {
            $item = $data->data[$data->position][$data->key];

            return $item instanceof Stub || array() === $item ? $data : $item;
        }
    }

    public function __isset($key)
    {
        return null !== $this->seek($key);
    }

    public function offsetExists($key)
    {
        return $this->__isset($key);
    }

    public function offsetGet($key)
    {
        return $this->__get($key);
    }

    public function offsetSet($key, $value)
    {
        throw new \BadMethodCallException(self::class.' objects are immutable.');
    }

    public function offsetUnset($key)
    {
        throw new \BadMethodCallException(self::class.' objects are immutable.');
    }

    public function __toString()
    {
        $value = $this->getValue();

        if (!is_array($value)) {
            return (string) $value;
        }

        return sprintf('%s (count=%d)', $this->getType(), count($value));
    }

    /**
     * @return array The raw data structure
     *
     * @deprecated since version 3.3. Use array or object access instead.
     */
    public function getRawData()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Use the array or object access instead.', __METHOD__));

        return $this->data;
    }

    /**
     * Returns a depth limited clone of $this.
     *
     * @param int $maxDepth The max dumped depth level
     *
     * @return self A clone of $this
     */
    public function withMaxDepth($maxDepth)
    {
        $data = clone $this;
        $data->maxDepth = (int) $maxDepth;

        return $data;
    }

    /**
     * Limits the number of elements per depth level.
     *
     * @param int $maxItemsPerDepth The max number of items dumped per depth level
     *
     * @return self A clone of $this
     */
    public function withMaxItemsPerDepth($maxItemsPerDepth)
    {
        $data = clone $this;
        $data->maxItemsPerDepth = (int) $maxItemsPerDepth;

        return $data;
    }

    /**
     * Enables/disables objects' identifiers tracking.
     *
     * @param bool $useRefHandles False to hide global ref. handles
     *
     * @return self A clone of $this
     */
    public function withRefHandles($useRefHandles)
    {
        $data = clone $this;
        $data->useRefHandles = $useRefHandles ? -1 : 0;

        return $data;
    }

    /**
     * Seeks to a specific key in nested data structures.
     *
     * @param string|int $key The key to seek to
     *
     * @return self|null A clone of $this of null if the key is not set
     */
    public function seek($key)
    {
        $item = $this->data[$this->position][$this->key];

        if ($item instanceof Stub && Stub::TYPE_REF === $item->type && !$item->position) {
            $item = $item->value;
        }
        if (!$item instanceof Stub || !$item->position) {
            return;
        }
        $keys = array($key);

        switch ($item->type) {
            case Stub::TYPE_OBJECT:
                $keys[] = Caster::PREFIX_DYNAMIC.$key;
                $keys[] = Caster::PREFIX_PROTECTED.$key;
                $keys[] = Caster::PREFIX_VIRTUAL.$key;
                $keys[] = "\0$item->class\0$key";
            case Stub::TYPE_ARRAY:
            case Stub::TYPE_RESOURCE:
                break;
            default:
                return;
        }

        $data = null;
        $children = $this->data[$item->position];

        foreach ($keys as $key) {
            if (isset($children[$key]) || array_key_exists($key, $children)) {
                $data = clone $this;
                $data->key = $key;
                $data->position = $item->position;
                break;
            }
        }

        return $data;
    }

    /**
     * Dumps data with a DumperInterface dumper.
     */
    public function dump(DumperInterface $dumper)
    {
        $refs = array(0);
        $this->dumpItem($dumper, new Cursor(), $refs, $this->data[$this->position][$this->key]);
    }

    /**
     * Depth-first dumping of items.
     *
     * @param DumperInterface $dumper The dumper being used for dumping
     * @param Cursor          $cursor A cursor used for tracking dumper state position
     * @param array           &$refs  A map of all references discovered while dumping
     * @param mixed           $item   A Stub object or the original value being dumped
     */
    private function dumpItem($dumper, $cursor, &$refs, $item)
    {
        $cursor->refIndex = 0;
        $cursor->softRefTo = $cursor->softRefHandle = $cursor->softRefCount = 0;
        $cursor->hardRefTo = $cursor->hardRefHandle = $cursor->hardRefCount = 0;
        $firstSeen = true;

        if (!$item instanceof Stub) {
            $cursor->attr = array();
            $type = gettype($item);
        } elseif (Stub::TYPE_REF === $item->type) {
            if ($item->handle) {
                if (!isset($refs[$r = $item->handle - (PHP_INT_MAX >> 1)])) {
                    $cursor->refIndex = $refs[$r] = $cursor->refIndex ?: ++$refs[0];
                } else {
                    $firstSeen = false;
                }
                $cursor->hardRefTo = $refs[$r];
                $cursor->hardRefHandle = $this->useRefHandles & $item->handle;
                $cursor->hardRefCount = $item->refCount;
            }
            $cursor->attr = $item->attr;
            $type = $item->class ?: gettype($item->value);
            $item = $item->value;
        }
        if ($item instanceof Stub) {
            if ($item->refCount) {
                if (!isset($refs[$r = $item->handle])) {
                    $cursor->refIndex = $refs[$r] = $cursor->refIndex ?: ++$refs[0];
                } else {
                    $firstSeen = false;
                }
                $cursor->softRefTo = $refs[$r];
            }
            $cursor->softRefHandle = $this->useRefHandles & $item->handle;
            $cursor->softRefCount = $item->refCount;
            $cursor->attr = $item->attr;
            $cut = $item->cut;

            if ($item->position && $firstSeen) {
                $children = $this->data[$item->position];

                if ($cursor->stop) {
                    if ($cut >= 0) {
                        $cut += count($children);
                    }
                    $children = array();
                }
            } else {
                $children = array();
            }
            switch ($item->type) {
                case Stub::TYPE_STRING:
                    $dumper->dumpString($cursor, $item->value, Stub::STRING_BINARY === $item->class, $cut);
                    break;

                case Stub::TYPE_ARRAY:
                    $item = clone $item;
                    $item->type = $item->class;
                    $item->class = $item->value;
                    // No break;
                case Stub::TYPE_OBJECT:
                case Stub::TYPE_RESOURCE:
                    $withChildren = $children && $cursor->depth !== $this->maxDepth && $this->maxItemsPerDepth;
                    $dumper->enterHash($cursor, $item->type, $item->class, $withChildren);
                    if ($withChildren) {
                        $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $item->type, null !== $item->class);
                    } elseif ($children && 0 <= $cut) {
                        $cut += count($children);
                    }
                    $dumper->leaveHash($cursor, $item->type, $item->class, $withChildren, $cut);
                    break;

                default:
                    throw new \RuntimeException(sprintf('Unexpected Stub type: %s', $item->type));
            }
        } elseif ('array' === $type) {
            $dumper->enterHash($cursor, Cursor::HASH_INDEXED, 0, false);
            $dumper->leaveHash($cursor, Cursor::HASH_INDEXED, 0, false, 0);
        } elseif ('string' === $type) {
            $dumper->dumpString($cursor, $item, false, 0);
        } else {
            $dumper->dumpScalar($cursor, $type, $item);
        }
    }

    /**
     * Dumps children of hash structures.
     *
     * @param DumperInterface $dumper
     * @param Cursor          $parentCursor The cursor of the parent hash
     * @param array           &$refs        A map of all references discovered while dumping
     * @param array           $children     The children to dump
     * @param int             $hashCut      The number of items removed from the original hash
     * @param string          $hashType     A Cursor::HASH_* const
     * @param bool            $dumpKeys     Whether keys should be dumped or not
     *
     * @return int The final number of removed items
     */
    private function dumpChildren($dumper, $parentCursor, &$refs, $children, $hashCut, $hashType, $dumpKeys)
    {
        $cursor = clone $parentCursor;
        ++$cursor->depth;
        $cursor->hashType = $hashType;
        $cursor->hashIndex = 0;
        $cursor->hashLength = count($children);
        $cursor->hashCut = $hashCut;
        foreach ($children as $key => $child) {
            $cursor->hashKeyIsBinary = isset($key[0]) && !preg_match('//u', $key);
            $cursor->hashKey = $dumpKeys ? $key : null;
            $this->dumpItem($dumper, $cursor, $refs, $child);
            if (++$cursor->hashIndex === $this->maxItemsPerDepth || $cursor->stop) {
                $parentCursor->stop = true;

                return $hashCut >= 0 ? $hashCut + $cursor->hashLength - $cursor->hashIndex : $hashCut;
            }
        }

        return $hashCut;
    }
}
