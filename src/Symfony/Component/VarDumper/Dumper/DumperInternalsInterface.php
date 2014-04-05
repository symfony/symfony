<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

/**
 * DumperInterface used by Data objects.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface DumperInternalsInterface
{
    /**
     * Dumps a scalar value.
     *
     * @param Cursor $cursor The Cursor position in the dump.
     * @param string $type   The PHP type of the value being dumped.
     * @param scalar $value  The scalar value being dumped.
     */
    public function dumpScalar(Cursor $cursor, $type, $value);

    /**
     * Dumps a string.
     *
     * @param Cursor $cursor The Cursor position in the dump.
     * @param string $str    The string being dumped.
     * @param bool   $bin    Whether $str is UTF-8 or binary encoded.
     * @param int    $cut    The number of characters $str has been cut by.
     */
    public function dumpString(Cursor $cursor, $str, $bin, $cut);

    /**
     * Dumps while entering an array.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param int    $count    The number of items in the original array.
     * @param bool   $indexed  When the array is indexed or associative.
     * @param bool   $hasChild When the dump of the array has child item.
     */
    public function enterArray(Cursor $cursor, $count, $indexed, $hasChild);

    /**
     * Dumps while leaving an array.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param int    $count    The number of items in the original array.
     * @param bool   $indexed  Whether the array is indexed or associative.
     * @param bool   $hasChild When the dump of the array has child item.
     * @param int    $cut      The number of items the array has been cut by.
     */
    public function leaveArray(Cursor $cursor, $count, $indexed, $hasChild, $cut);

    /**
     * Dumps while entering an object.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param string $class    The class of the object.
     * @param bool   $hasChild When the dump of the object has child item.
     */
    public function enterObject(Cursor $cursor, $class, $hasChild);

    /**
     * Dumps while leaving an object.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param string $class    The class of the object.
     * @param bool   $hasChild When the dump of the object has child item.
     * @param int    $cut      The number of items the object has been cut by.
     */
    public function leaveObject(Cursor $cursor, $class, $hasChild, $cut);

    /**
     * Dumps while entering a resource.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param string $res      The resource type.
     * @param bool   $hasChild When the dump of the resource has child item.
     */
    public function enterResource(Cursor $cursor, $res, $hasChild);

    /**
     * Dumps while leaving a resource.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param string $res      The resource type.
     * @param bool   $hasChild When the dump of the resource has child item.
     * @param int    $cut      The number of items the resource has been cut by.
     */
    public function leaveResource(Cursor $cursor, $res, $hasChild, $cut);
}
