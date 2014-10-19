<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts SPL related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SplCaster
{
    public static function castArrayObject(\ArrayObject $c, array $a, Stub $stub, $isNested)
    {
        $class = $stub->class;
        $flags = $c->getFlags();

        $b = array(
            "\0~\0flag::STD_PROP_LIST" => (bool) ($flags & \ArrayObject::STD_PROP_LIST),
            "\0~\0flag::ARRAY_AS_PROPS" => (bool) ($flags & \ArrayObject::ARRAY_AS_PROPS),
            "\0~\0iteratorClass" => $c->getIteratorClass(),
            "\0~\0storage" => $c->getArrayCopy(),
        );

        if ($class === 'ArrayObject') {
            $a = $b;
        } else {
            if (!($flags & \ArrayObject::STD_PROP_LIST)) {
                $c->setFlags(\ArrayObject::STD_PROP_LIST);

                if ($a = (array) $c) {
                    $class = new \ReflectionClass($class);
                    foreach ($a as $k => $p) {
                        if (!isset($k[0]) || ("\0" !== $k[0] && !$class->hasProperty($k))) {
                            unset($a[$k]);
                            $a["\0+\0".$k] = $p;
                        }
                    }
                }

                $c->setFlags($flags);
            }

            $a += $b;
        }

        return $a;
    }

    public static function castHeap(\Iterator $c, array $a, Stub $stub, $isNested)
    {
        $a += array(
            "\0~\0heap" => iterator_to_array(clone $c),
        );

        return $a;
    }

    public static function castDoublyLinkedList(\SplDoublyLinkedList $c, array $a, Stub $stub, $isNested)
    {
        $mode = $c->getIteratorMode();
        $c->setIteratorMode(\SplDoublyLinkedList::IT_MODE_KEEP | $mode & ~\SplDoublyLinkedList::IT_MODE_DELETE);

        $a += array(
            "\0~\0mode" => new ConstStub((($mode & \SplDoublyLinkedList::IT_MODE_LIFO) ? 'IT_MODE_LIFO' : 'IT_MODE_FIFO').' | '.(($mode & \SplDoublyLinkedList::IT_MODE_KEEP) ? 'IT_MODE_KEEP' : 'IT_MODE_DELETE'), $mode),
            "\0~\0dllist" => iterator_to_array($c),
        );
        $c->setIteratorMode($mode);

        return $a;
    }

    public static function castFixedArray(\SplFixedArray $c, array $a, Stub $stub, $isNested)
    {
        $a += array(
            "\0~\0storage" => $c->toArray(),
        );

        return $a;
    }

    public static function castObjectStorage(\SplObjectStorage $c, array $a, Stub $stub, $isNested)
    {
        $storage = array();
        unset($a["\0+\0\0gcdata"]); // Don't hit https://bugs.php.net/65967

        foreach ($c as $obj) {
            $storage[spl_object_hash($obj)] = array(
                'object' => $obj,
                'info' => $c->getInfo(),
             );
        }

        $a += array(
            "\0~\0storage" => $storage,
        );

        return $a;
    }
}
