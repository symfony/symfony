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
    private $maxDepth = -1;
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
     * @param int  $maxDepth         The max dumped depth level.
     * @param int  $maxItemsPerDepth The max number of items dumped per depth level.
     * @param bool $useRefHandles    False to hide ref. handles.
     *
     * @return self A depth limited clone of $this.
     */
    public function getLimitedClone($maxDepth, $maxItemsPerDepth, $useRefHandles = true)
    {
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
                    $dumper->enterHash($cursor, $item->class, $item->value, (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $item->class);
                    $dumper->leaveHash($cursor, $item->class, $item->value, (bool) $children, $cut);
                    break;

                case Stub::TYPE_OBJECT:
                case Stub::TYPE_RESOURCE:
                    $dumper->enterHash($cursor, $item->type, $item->class, (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $item->type);
                    $dumper->leaveHash($cursor, $item->type, $item->class, (bool) $children, $cut);
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
        if ($children) {
            if ($parentCursor->depth !== $this->maxDepth && $this->maxItemsPerDepth) {
                $cursor = clone $parentCursor;
                ++$cursor->depth;
                $cursor->hashType = $hashType;
                $cursor->hashIndex = 0;
                $cursor->hashLength = count($children);
                $cursor->hashCut = $hashCut;
                foreach ($children as $key => $child) {
                    $cursor->hashKeyIsBinary = isset($key[0]) && !preg_match('//u', $key);
                    $cursor->hashKey = $cursor->hashKeyIsBinary ? self::utf8Encode($key) : $key;
                    $this->dumpItem($dumper, $cursor, $refs, $child);
                    if (++$cursor->hashIndex === $this->maxItemsPerDepth || $cursor->stop) {
                        $parentCursor->stop = true;

                        return $hashCut >= 0 ? $hashCut + $cursor->hashLength - $cursor->hashIndex : $hashCut;
                    }
                }
            } elseif ($hashCut >= 0) {
                $hashCut += count($children);
            }
        }

        return $hashCut;
    }

    /**
     * Portable variant of utf8_encode()
     *
     * @param string $s
     *
     * @return string
     *
     * @internal
     */
    public static function utf8Encode($s)
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($s, 'UTF-8', 'CP1252');
        }

        $s .= $s;
        $len = strlen($s);

        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80": $s[$j] = $s[$i]; break;
                case $s[$i] < "\xC0": $s[$j] = "\xC2"; $s[++$j] = $s[$i]; break;
                default: $s[$j] = "\xC3"; $s[++$j] = chr(ord($s[$i]) - 64); break;
            }
        }

        return substr($s, 0, $j);
    }
}
