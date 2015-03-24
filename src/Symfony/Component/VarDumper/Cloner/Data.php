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
class Data
{
    private $data;
    private $maxDepth = 20;
    private $maxItemsPerDepth = -1;
    private $useRefHandles = -1;

    /**
     * @param array $data A array as returned by ClonerInterface::cloneVar().
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array The raw data structure.
     */
    public function getRawData()
    {
        return $this->data;
    }

    /**
     * Returns a depth limited clone of $this.
     *
     * @param int $maxDepth The max dumped depth level.
     *
     * @return self A clone of $this.
     */
    public function withMaxDepth($maxDepth)
    {
        $data = clone $this;
        $data->maxDepth = (int) $maxDepth;

        return $data;
    }

    /**
     * Limits the numbers of elements per depth level.
     *
     * @param int $maxItemsPerDepth The max number of items dumped per depth level.
     *
     * @return self A clone of $this.
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
     * @param bool $useRefHandles False to hide global ref. handles.
     *
     * @return self A clone of $this.
     */
    public function withRefHandles($useRefHandles)
    {
        $data = clone $this;
        $data->useRefHandles = $useRefHandles ? -1 : 0;

        return $data;
    }

    /**
     * Returns a depth limited clone of $this.
     *
     * @param int  $maxDepth         The max dumped depth level.
     * @param int  $maxItemsPerDepth The max number of items dumped per depth level.
     * @param bool $useRefHandles    False to hide ref. handles.
     *
     * @return self A depth limited clone of $this.
     *
     * @deprecated since Symfony 2.7, to be removed in 3.0. Use withMaxDepth, withMaxItemsPerDepth or withRefHandles instead.
     */
    public function getLimitedClone($maxDepth, $maxItemsPerDepth, $useRefHandles = true)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.7 and will be removed in 3.0. Use withMaxDepth, withMaxItemsPerDepth or withRefHandles methods instead.', E_USER_DEPRECATED);

        $data = clone $this;
        $data->maxDepth = (int) $maxDepth;
        $data->maxItemsPerDepth = (int) $maxItemsPerDepth;
        $data->useRefHandles = $useRefHandles ? -1 : 0;

        return $data;
    }

    /**
     * Dumps data with a DumperInterface dumper.
     */
    public function dump(DumperInterface $dumper)
    {
        $refs = array(0);
        $this->dumpItem($dumper, new Cursor(), $refs, $this->data[0][0]);
    }

    /**
     * Depth-first dumping of items.
     *
     * @param DumperInterface $dumper The dumper being used for dumping.
     * @param Cursor          $cursor A cursor used for tracking dumper state position.
     * @param array           &$refs  A map of all references discovered while dumping.
     * @param mixed           $item   A Stub object or the original value being dumped.
     */
    private function dumpItem($dumper, $cursor, &$refs, $item)
    {
        $cursor->refIndex = 0;
        $cursor->softRefTo = $cursor->softRefHandle = $cursor->softRefCount = 0;
        $cursor->hardRefTo = $cursor->hardRefHandle = $cursor->hardRefCount = 0;
        $firstSeen = true;

        if (!$item instanceof Stub) {
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
                        $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $item->type);
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
     * @param Cursor          $parentCursor The cursor of the parent hash.
     * @param array           &$refs        A map of all references discovered while dumping.
     * @param array           $children     The children to dump.
     * @param int             $hashCut      The number of items removed from the original hash.
     * @param string          $hashType     A Cursor::HASH_* const.
     *
     * @return int The final number of removed items.
     */
    private function dumpChildren($dumper, $parentCursor, &$refs, $children, $hashCut, $hashType)
    {
        $cursor = clone $parentCursor;
        ++$cursor->depth;
        $cursor->hashType = $hashType;
        $cursor->hashIndex = 0;
        $cursor->hashLength = count($children);
        $cursor->hashCut = $hashCut;
        foreach ($children as $key => $child) {
            $cursor->hashKeyIsBinary = isset($key[0]) && !preg_match('//u', $key);
            $cursor->hashKey = $key;
            $this->dumpItem($dumper, $cursor, $refs, $child);
            if (++$cursor->hashIndex === $this->maxItemsPerDepth || $cursor->stop) {
                $parentCursor->stop = true;

                return $hashCut >= 0 ? $hashCut + $cursor->hashLength - $cursor->hashIndex : $hashCut;
            }
        }

        return $hashCut;
    }
}
