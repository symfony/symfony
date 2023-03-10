<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

require __DIR__.'/uniontype_classes.php';
require __DIR__.'/autowiring_classes_80.php';
require __DIR__.'/intersectiontype_classes.php';
if (\PHP_VERSION_ID >= 80200) {
    require __DIR__.'/compositetype_classes.php';
}

// @deprecated since Symfony 6.3, to be removed in 7.0
class FooAnnotation
{
    /**
     * @required
     */
    public function cloneFoo(): static
    {
        return clone $this;
    }
}

class Foo
{
    public static int $counter = 0;

    #[Required]
    public function cloneFoo(\stdClass $bar = null): static
    {
        ++self::$counter;

        return clone $this;
    }
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
    public function __construct(A $k, $foo, Dunglas $dunglas, array $bar)
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

// @deprecated since Symfony 6.3, to be removed in 7.0
class SetterInjectionCollisionAnnotation
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

class SetterInjectionCollision
{
    #[Required]
    public function setMultipleInstancesForOneArg(CollisionInterface $collision)
    {
        // The CollisionInterface cannot be autowired - there are multiple

        // should throw an exception
    }
}

// @deprecated since Symfony 6.3, to be removed in 7.0
class SetterInjectionAnnotation extends SetterInjectionParentAnnotation
{

    /**
     * @required
     */
    public function setFoo(Foo $foo)
    {
        // should be called
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

// @deprecated since Symfony 6.3, to be removed in 7.0
class SetterInjection extends SetterInjectionParent
{
    #[Required]
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
}

// @deprecated since Symfony 6.3, to be removed in 7.0
class WitherAnnotation
{
    public $foo;

    /**
     * @required
     */
    public function setFoo(FooAnnotation $foo)
    {
    }

    /**
     * @required
     */
    public function withFoo1(FooAnnotation $foo): static
    {
        return $this->withFoo2($foo);
    }

    /**
     * @required
     */
    public function withFoo2(FooAnnotation $foo): static
    {
        $new = clone $this;
        $new->foo = $foo;

        return $new;
    }
}

class Wither
{
    public $foo;

    #[Required]
    public function setFoo(Foo $foo)
    {
    }

    #[Required]
    public function withFoo1(Foo $foo): static
    {
        return $this->withFoo2($foo);
    }

    #[Required]
    public function withFoo2(Foo $foo): static
    {
        $new = clone $this;
        $new->foo = $foo;

        return $new;
    }
}

// @deprecated since Symfony 6.3, to be removed in 7.0
class SetterInjectionParentAnnotation
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

class SetterInjectionParent
{
    #[Required]
    public function setDependencies(Foo $foo, A $a)
    {
        // should be called
    }

    public function notASetter(A $a)
    {
        // #[Required] should be ignored when the child does not add @inheritdoc
    }

    #[Required]
    public function setWithCallsConfigured(A $a)
    {
    }

    #[Required]
    public function setChildMethodWithoutDocBlock(A $a)
    {
    }
}

class NotWireable
{
    public function setNotAutowireable(NotARealClass $n)
    {
    }

    public function setNotAutowireableBecauseOfATypo(lesTilleuls $sam)
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

    #[Required]
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

// @deprecated since Symfony 6.3, to be removed in 7.0
class ScalarSetterAnnotation
{
    /**
     * @required
     */
    public function setDefaultLocale($defaultLocale)
    {
    }
}

class ScalarSetter
{
    #[Required]
    public function setDefaultLocale($defaultLocale)
    {
    }
}

interface DecoratorInterface
{
}

class DecoratorImpl implements DecoratorInterface
{
}

class Decorated implements DecoratorInterface
{
    public function __construct($quz = null, \NonExistent $nonExistent = null, DecoratorInterface $decorated = null, array $foo = [])
    {
    }
}

class Decorator implements DecoratorInterface
{
    public function __construct(LoggerInterface $logger, DecoratorInterface $decorated)
    {
    }
}

class DecoratedDecorator implements DecoratorInterface
{
    public function __construct(DecoratorInterface $decorator)
    {
    }
}

class NonAutowirableDecorator implements DecoratorInterface
{
    public function __construct(LoggerInterface $logger, DecoratorInterface $decorated1, DecoratorInterface $decorated2)
    {
    }
}

final class ElsaAction
{
    public function __construct(NotExisting $notExisting)
    {
    }
}

class StaticConstructor
{
    public function __construct(private string $bar)
    {
    }

    public function getBar(): string
    {
        return $this->bar;
    }

    public static function create(string $foo): static
    {
        return new self($foo);
    }
}

class AAndIInterfaceConsumer
{
    public function __construct(
        #[Autowire(service: 'foo', lazy: true)]
        AInterface&IInterface $logger,
    ) {
    }
}

interface SingleMethodInterface
{
    public function theMethod();
}
