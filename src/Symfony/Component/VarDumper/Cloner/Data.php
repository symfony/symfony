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
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;

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
    private $context = [];

    /**
     * @param array $data An array as returned by ClonerInterface::cloneVar()
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return string|null The type of the value
     */
    public function getType()
    {
        $item = $this->data[$this->position][$this->key];

        if ($item instanceof Stub && Stub::TYPE_REF === $item->type && !$item->position) {
            $item = $item->value;
        }
        if (!$item instanceof Stub) {
            return \gettype($item);
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

        return null;
    }

    /**
     * @param array|bool $recursive Whether values should be resolved recursively or not
     *
     * @return string|int|float|bool|array|Data[]|null A native representation of the original value
     */
    public function getValue($recursive = false)
    {
        $item = $this->data[$this->position][$this->key];

        if ($item instanceof Stub && Stub::TYPE_REF === $item->type && !$item->position) {
            $item = $item->value;
        }
        if (!($item = $this->getStub($item)) instanceof Stub) {
            return $item;
        }
        if (Stub::TYPE_STRING === $item->type) {
            return $item->value;
        }

        $children = $item->position ? $this->data[$item->position] : [];

        foreach ($children as $k => $v) {
            if ($recursive && !($v = $this->getStub($v)) instanceof Stub) {
                continue;
            }
            $children[$k] = clone $this;
            $children[$k]->key = $k;
            $children[$k]->position = $item->position;

            if ($recursive) {
                if (Stub::TYPE_REF === $v->type && ($v = $this->getStub($v->value)) instanceof Stub) {
                    $recursive = (array) $recursive;
                    if (isset($recursive[$v->position])) {
                        continue;
                    }
                    $recursive[$v->position] = true;
                }
                $children[$k] = $children[$k]->getValue($recursive);
            }
        }

        return $children;
    }

    /**
     * @return int
     */
    public function count()
    {
        return \count($this->getValue());
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        if (!\is_array($value = $this->getValue())) {
            throw new \LogicException(sprintf('%s object holds non-iterable type "%s".', self::class, \gettype($value)));
        }

        yield from $value;
    }

    public function __get(string $key)
    {
        if (null !== $data = $this->seek($key)) {
            $item = $this->getStub($data->data[$data->position][$data->key]);

            return $item instanceof Stub || [] === $item ? $data : $item;
        }

        return null;
    }

    /**
     * @return bool
     */
    public function __isset(string $key)
    {
        return null !== $this->seek($key);
    }

    /**
     * @return bool
     */
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

    /**
     * @return string
     */
    public function __toString()
    {
        $value = $this->getValue();

        if (!\is_array($value)) {
            return (string) $value;
        }

        return sprintf('%s (count=%d)', $this->getType(), \count($value));
    }

    /**
     * Returns a depth limited clone of $this.
     *
     * @return static
     */
    public function withMaxDepth(int $maxDepth)
    {
        $data = clone $this;
        $data->maxDepth = (int) $maxDepth;

        return $data;
    }

    /**
     * Limits the number of elements per depth level.
     *
     * @return static
     */
    public function withMaxItemsPerDepth(int $maxItemsPerDepth)
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
     * @return static
     */
    public function withRefHandles(bool $useRefHandles)
    {
        $data = clone $this;
        $data->useRefHandles = $useRefHandles ? -1 : 0;

        return $data;
    }

    /**
     * @return static
     */
    public function withContext(array $context)
    {
        $data = clone $this;
        $data->context = $context;

        return $data;
    }

    /**
     * Seeks to a specific key in nested data structures.
     *
     * @param string|int $key The key to seek to
     *
     * @return static|null Null if the key is not set
     */
    public function seek($key)
    {
        $item = $this->data[$this->position][$this->key];

        if ($item instanceof Stub && Stub::TYPE_REF === $item->type && !$item->position) {
            $item = $item->value;
        }
        if (!($item = $this->getStub($item)) instanceof Stub || !$item->position) {
            return null;
        }
        $keys = [$key];

        switch ($item->type) {
            case Stub::TYPE_OBJECT:
                $keys[] = Caster::PREFIX_DYNAMIC.$key;
                $keys[] = Caster::PREFIX_PROTECTED.$key;
                $keys[] = Caster::PREFIX_VIRTUAL.$key;
                $keys[] = "\0$item->class\0$key";
                // no break
            case Stub::TYPE_ARRAY:
            case Stub::TYPE_RESOURCE:
                break;
            default:
                return null;
        }

        $data = null;
        $children = $this->data[$item->position];

        foreach ($keys as $key) {
            if (isset($children[$key]) || \array_key_exists($key, $children)) {
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
        $refs = [0];
        $cursor = new Cursor();

        if ($cursor->attr = $this->context[SourceContextProvider::class] ?? []) {
            $cursor->attr['if_links'] = true;
            $cursor->hashType = -1;
            $dumper->dumpScalar($cursor, 'default', '^');
            $cursor->attr = ['if_links' => true];
            $dumper->dumpScalar($cursor, 'default', ' ');
            $cursor->hashType = 0;
        }

        $this->dumpItem($dumper, $cursor, $refs, $this->data[$this->position][$this->key]);
    }

    /**
     * Depth-first dumping of items.
     *
     * @param mixed $item A Stub object or the original value being dumped
     */
    private function dumpItem(DumperInterface $dumper, Cursor $cursor, array &$refs, $item)
    {
        $cursor->refIndex = 0;
        $cursor->softRefTo = $cursor->softRefHandle = $cursor->softRefCount = 0;
        $cursor->hardRefTo = $cursor->hardRefHandle = $cursor->hardRefCount = 0;
        $firstSeen = true;

        if (!$item instanceof Stub) {
            $cursor->attr = [];
            $type = \gettype($item);
            if ($item && 'array' === $type) {
                $item = $this->getStub($item);
            }
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
            $type = $item->class ?: \gettype($item->value);
            $item = $this->getStub($item->value);
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
                        $cut += \count($children);
                    }
                    $children = [];
                }
            } else {
                $children = [];
            }
            switch ($item->type) {
                case Stub::TYPE_STRING:
                    $dumper->dumpString($cursor, $item->value, Stub::STRING_BINARY === $item->class, $cut);
                    break;

                case Stub::TYPE_ARRAY:
                    $item = clone $item;
                    $item->type = $item->class;
                    $item->class = $item->value;
                    // no break
                case Stub::TYPE_OBJECT:
                case Stub::TYPE_RESOURCE:
                    $withChildren = $children && $cursor->depth !== $this->maxDepth && $this->maxItemsPerDepth;
                    $dumper->enterHash($cursor, $item->type, $item->class, $withChildren);
                    if ($withChildren) {
                        if ($cursor->skipChildren) {
                            $withChildren = false;
                            $cut = -1;
                        } else {
                            $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $item->type, null !== $item->class);
                        }
                    } elseif ($children && 0 <= $cut) {
                        $cut += \count($children);
                    }
                    $cursor->skipChildren = false;
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
     * @return int The final number of removed items
     */
    private function dumpChildren(DumperInterface $dumper, Cursor $parentCursor, array &$refs, array $children, int $hashCut, int $hashType, bool $dumpKeys): int
    {
        $cursor = clone $parentCursor;
        ++$cursor->depth;
        $cursor->hashType = $hashType;
        $cursor->hashIndex = 0;
        $cursor->hashLength = \count($children);
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

    private function getStub($item)
    {
        if (!$item || !\is_array($item)) {
            return $item;
        }

        $stub = new Stub();
        $stub->type = Stub::TYPE_ARRAY;
        foreach ($item as $stub->class => $stub->position) {
        }
        if (isset($item[0])) {
            $stub->cut = $item[0];
        }
        $stub->value = $stub->cut + ($stub->position ? \count($this->data[$stub->position]) : 0);

        return $stub;
    }
}
