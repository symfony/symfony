<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

class Foo
{
}

class Bar
{
    public function __construct(Foo $foo)
    {
    }
}

interface AInterface
{
}

class A implements AInterface
{
    public static function create(Foo $foo)
    {
    }
}

class B extends A
{
}

class C
{
    public function __construct(A $a)
    {
    }
}

interface DInterface
{
}

interface EInterface extends DInterface
{
}

interface IInterface
{
}

class I implements IInterface
{
}

class F extends I implements EInterface
{
}

class G
{
    public function __construct(DInterface $d, EInterface $e, IInterface $i)
    {
    }
}

class H
{
    public function __construct(B $b, DInterface $d)
    {
    }
}

class D
{
    public function __construct(A $a, DInterface $d)
    {
    }
}

class E
{
    public function __construct(D $d = null)
    {
    }
}

class J
{
    public function __construct(I $i)
    {
    }
}

class K
{
    public function __construct(IInterface $i)
    {
    }
}

interface CollisionInterface
{
}

class CollisionA implements CollisionInterface
{
}

class CollisionB implements CollisionInterface
{
}

class CannotBeAutowired
{
    public function __construct(CollisionInterface $collision)
    {
    }
}

class CannotBeAutowiredForwardOrder
{
    public function __construct(CollisionA $a, CollisionInterface $b, CollisionB $c)
    {
    }
}

class CannotBeAutowiredReverseOrder
{
    public function __construct(CollisionA $a, CollisionB $c, CollisionInterface $b)
    {
    }
}

class Lille
{
}

class Dunglas
{
    public function __construct(Lille $l)
    {
    }
}

class LesTilleuls
{
    public function __construct(Dunglas $j, Dunglas $k)
    {
    }
}

class OptionalParameter
{
    public function __construct(CollisionInterface $c = null, A $a, Foo $f = null)
    {
    }
}

class BadTypeHintedArgument
{
    public function __construct(Dunglas $k, NotARealClass $r)
    {
    }
}
class BadParentTypeHintedArgument
{
    public function __construct(Dunglas $k, OptionalServiceClass $r)
    {
    }
}
class NotGuessableArgument
{
    public function __construct(Foo $k)
    {
    }
}
class NotGuessableArgumentForSubclass
{
    public function __construct(A $k)
    {
    }
}
class MultipleArguments
{
    public function __construct(A $k, $foo, Dunglas $dunglas)
    {
    }
}

class MultipleArgumentsOptionalScalar
{
    public function __construct(A $a, $foo = 'default_val', Lille $lille = null)
    {
    }
}
class MultipleArgumentsOptionalScalarLast
{
    public function __construct(A $a, Lille $lille, $foo = 'some_val')
    {
    }
}
class MultipleArgumentsOptionalScalarNotReallyOptional
{
    public function __construct(A $a, $foo = 'default_val', Lille $lille)
    {
    }
}

/*
 * Classes used for testing createResourceForClass
 */
class ClassForResource
{
    public function __construct($foo, Bar $bar = null)
    {
    }

    public function setBar(Bar $bar)
    {
    }
}
class IdenticalClassResource extends ClassForResource
{
}

class ClassChangedConstructorArgs extends ClassForResource
{
    public function __construct($foo, Bar $bar, $baz)
    {
    }
}

class SetterInjectionCollision
{
    /**
     * @required
     */
    public function setMultipleInstancesForOneArg(CollisionInterface $collision)
    {
        // The CollisionInterface cannot be autowired - there are multiple

        // should throw an exception
    }
}

class SetterInjection extends SetterInjectionParent
{
    /**
     * @required
     */
    public function setFoo(Foo $foo)
    {
        // should be called
    }

    /** @inheritdoc*/ // <- brackets are missing on purpose
    public function setDependencies(Foo $foo, A $a)
    {
        // should be called
    }

    /** {@inheritdoc} */
    public function setWithCallsConfigured(A $a)
    {
        // this method has a calls configured on it
    }

    public function notASetter(A $a)
    {
        // should be called only when explicitly specified
    }

    /**
     * @required*/
    public function setChildMethodWithoutDocBlock(A $a)
    {
    }
}

class SetterInjectionParent
{
    /** @required*/
    public function setDependencies(Foo $foo, A $a)
    {
        // should be called
    }

    public function notASetter(A $a)
    {
        // @required should be ignored when the child does not add @inheritdoc
    }

    /**	@required <tab> prefix is on purpose */
    public function setWithCallsConfigured(A $a)
    {
    }

    /** @required */
    public function setChildMethodWithoutDocBlock(A $a)
    {
    }
}

class NotWireable
{
    public function setNotAutowireable(NotARealClass $n)
    {
    }

    public function setBar()
    {
    }

    public function setOptionalNotAutowireable(NotARealClass $n = null)
    {
    }

    public function setDifferentNamespace(\stdClass $n)
    {
    }

    public function setOptionalNoTypeHint($foo = null)
    {
    }

    public function setOptionalArgNoAutowireable($other = 'default_val')
    {
    }

    /** @required */
    protected function setProtectedMethod(A $a)
    {
    }
}

class PrivateConstructor
{
    private function __construct()
    {
    }
}

class ScalarSetter
{
    /**
     * @required
     */
    public function setDefaultLocale($defaultLocale)
    {
    }
}
