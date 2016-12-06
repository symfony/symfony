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
        $prefix = Caster::PREFIX_VIRTUAL;
        $class = $stub->class;
        $flags = $c->getFlags();

        $b = array(
            $prefix.'flag::STD_PROP_LIST' => (bool) ($flags & \ArrayObject::STD_PROP_LIST),
            $prefix.'flag::ARRAY_AS_PROPS' => (bool) ($flags & \ArrayObject::ARRAY_AS_PROPS),
            $prefix.'iteratorClass' => $c->getIteratorClass(),
            $prefix.'storage' => $c->getArrayCopy(),
        );

        if ($class === 'ArrayObject') {
            $a = $b;
        } else {
            if (!($flags & \ArrayObject::STD_PROP_LIST)) {
                $c->setFlags(\ArrayObject::STD_PROP_LIST);
                $a = Caster::castObject($c, new \ReflectionClass($class));
                $c->setFlags($flags);
            }

            $a += $b;
        }

        return $a;
    }

    public static function castHeap(\Iterator $c, array $a, Stub $stub, $isNested)
    {
        $a += array(
            Caster::PREFIX_VIRTUAL.'heap' => iterator_to_array(clone $c),
        );

        return $a;
    }

    public static function castDoublyLinkedList(\SplDoublyLinkedList $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;
        $mode = $c->getIteratorMode();
        $c->setIteratorMode(\SplDoublyLinkedList::IT_MODE_KEEP | $mode & ~\SplDoublyLinkedList::IT_MODE_DELETE);

        $a += array(
            $prefix.'mode' => new ConstStub((($mode & \SplDoublyLinkedList::IT_MODE_LIFO) ? 'IT_MODE_LIFO' : 'IT_MODE_FIFO').' | '.(($mode & \SplDoublyLinkedList::IT_MODE_DELETE) ? 'IT_MODE_DELETE' : 'IT_MODE_KEEP'), $mode),
            $prefix.'dllist' => iterator_to_array($c),
        );
        $c->setIteratorMode($mode);

        return $a;
    }

    public static function castFixedArray(\SplFixedArray $c, array $a, Stub $stub, $isNested)
    {
        $a += array(
            Caster::PREFIX_VIRTUAL.'storage' => $c->toArray(),
        );

        return $a;
    }

    public static function castObjectStorage(\SplObjectStorage $c, array $a, Stub $stub, $isNested)
    {
        $storage = array();
        unset($a[Caster::PREFIX_DYNAMIC."\0gcdata"]); // Don't hit https://bugs.php.net/65967

        foreach ($c as $obj) {
            $storage[spl_object_hash($obj)] = array(
                'object' => $obj,
                'info' => $c->getInfo(),
             );
        }

        $a += array(
            Caster::PREFIX_VIRTUAL.'storage' => $storage,
        );

        return $a;
    }
}
