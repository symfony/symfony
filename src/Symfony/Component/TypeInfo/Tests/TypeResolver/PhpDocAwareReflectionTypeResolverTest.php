<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\TypeResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\AnotherNamespace\DummyInDifferentNs;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyUsingTraitInDifferentNs;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyUsingTraitWithUses;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithPhpDoc;
use Symfony\Component\TypeInfo\Tests\Fixtures\PhpDocInheritance;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\PhpDocAwareReflectionTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

require_once __DIR__.'/../Fixtures/PhpDocInheritance.php';

class PhpDocAwareReflectionTypeResolverTest extends TestCase
{
    #[DataProvider('readPhpDocDataProvider')]
    public function testReadPhpDoc(Type $expected, \Reflector $reflector)
    {
        $resolver = new PhpDocAwareReflectionTypeResolver(TypeResolver::create(), new StringTypeResolver(), new TypeContextFactory(new StringTypeResolver()));

        $this->assertEquals($expected, $resolver->resolve($reflector));
    }

    /**
     * @return iterable<array{0: Type, 1: \Reflector}>
     */
    public static function readPhpDocDataProvider(): iterable
    {
        $reflection = new \ReflectionClass(DummyWithPhpDoc::class);

        yield [Type::array(Type::object(Dummy::class)), $reflection->getProperty('arrayOfDummies')];
        yield [Type::bool(), $reflection->getProperty('promoted')];
        yield [Type::string(), $reflection->getProperty('promotedVar')];
        yield [Type::string(), $reflection->getProperty('promotedVarAndParam')];
        yield [Type::object(Dummy::class), $reflection->getMethod('getNextDummy')];
        yield [Type::object(Dummy::class), $reflection->getMethod('getNextDummy')->getParameters()[0]];
        yield [Type::int(), $reflection->getProperty('aliasedInt')];
        yield [Type::arrayShape(["it's" => Type::int(), 'quoted key' => Type::int()]), $reflection->getProperty('arrayShapeWithQuotedKeys')];
        yield [Type::arrayShape(["it's" => Type::int(), 'quoted key' => Type::int()]), $reflection->getProperty('aliasedArrayShapeWithQuotedKeys')];
        yield [Type::string(), $reflection->getProperty('literal')];

        $reflection = new \ReflectionClass(DummyUsingTraitInDifferentNs::class);

        yield [Type::list(Type::template('T')), $reflection->getProperty('items')];
        yield [Type::list(Type::object(DummyInDifferentNs::class)), $reflection->getProperty('dummies')];
        yield [Type::object(DummyUsingTraitInDifferentNs::class), $reflection->getProperty('self')];
        yield [Type::object(AbstractDummy::class), $reflection->getProperty('parent')];
        yield [Type::object(DummyInDifferentNs::class), $reflection->getMethod('getNextDummy')];
        yield [Type::object(DummyInDifferentNs::class), $reflection->getMethod('getNextDummy')->getParameters()[0]];

        $reflection = new \ReflectionClass(DummyUsingTraitWithUses::class);

        yield [Type::object(DummyInDifferentNs::class), $reflection->getProperty('dummy')];
        yield [Type::object(Dummy::class), $reflection->getProperty('dummyFromTrait')];

        $items = Type::list(Type::object(PhpDocInheritance\Item::class));
        $listOfA = Type::list(Type::object(PhpDocInheritance\A::class));
        $listOfB = Type::list(Type::object(PhpDocInheritance\B::class));

        yield 'parent method' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getItems')];
        yield 'parent method without native types' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getUntyped')];
        yield 'native type added by the override' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getAddedNative')];
        yield 'docblock with a description only' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getDescribed')];
        yield 'docblock with another tag' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getDeprecated')];
        yield 'inheritDoc' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getInheritDoc')];
        yield 'own tag' => [Type::dict(Type::object(PhpDocInheritance\Item::class)), new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getOwn')];
        yield 'nullable native type made non-nullable' => [Type::array(), new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getNullable')];
        yield 'iterable native type made an array' => [Type::array(), new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getIterable')];
        yield 'native self type' => [Type::object(PhpDocInheritance\ChildDummy::class), new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getNativeSelf')];
        yield 'unsupported type' => [Type::union(Type::int(), Type::string()), new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getUnsupported')];
        yield 'private parent method' => [Type::array(), new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'getPrivate')];
        yield 'parameter of a parent method' => [$items, new \ReflectionParameter([PhpDocInheritance\ChildDummy::class, 'setTags'], 'tags')];
        yield 'renamed parameter of a parent method' => [$items, new \ReflectionParameter([PhpDocInheritance\ChildDummy::class, 'setRenamed'], 'values')];
        yield 'wider parameter of a parent method' => [Type::iterable(), new \ReflectionParameter([PhpDocInheritance\ChildDummy::class, 'setWidened'], 'tags')];
        yield 'return type of a partially documented method' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildDummy::class, 'merge')];
        yield 'undocumented parameter of a partially documented method' => [$items, new \ReflectionParameter([PhpDocInheritance\ChildDummy::class, 'merge'], 'first')];
        yield 'documented parameter of a partially documented method' => [Type::array(Type::object(PhpDocInheritance\Item::class)), new \ReflectionParameter([PhpDocInheritance\ChildDummy::class, 'merge'], 'second')];
        yield 'grandparent method' => [$items, new \ReflectionMethod(PhpDocInheritance\GrandChildDummy::class, 'getItems')];
        yield 'chain of overrides' => [$items, new \ReflectionMethod(PhpDocInheritance\GrandChildDummy::class, 'getChain')];

        yield 'interface method' => [$items, new \ReflectionMethod(PhpDocInheritance\ImplementsInterface::class, 'getItems')];
        yield 'parameter of an interface method' => [$items, new \ReflectionParameter([PhpDocInheritance\ImplementsInterface::class, 'setTags'], 'tags')];
        yield 'interface of the parent' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildOfImplementing::class, 'getItems')];
        yield 'first interface' => [$listOfA, new \ReflectionMethod(PhpDocInheritance\ImplementsAB::class, 'getItems')];
        yield 'first interface in another order' => [$listOfB, new \ReflectionMethod(PhpDocInheritance\ImplementsBA::class, 'getItems')];
        yield 'first documented interface' => [$listOfB, new \ReflectionMethod(PhpDocInheritance\ImplementsUndocumentedFirst::class, 'getItems')];
        yield 'parent interface' => [$listOfA, new \ReflectionMethod(PhpDocInheritance\ImplementsSub::class, 'getItems')];
        yield 'parent before interface' => [$listOfA, new \ReflectionMethod(PhpDocInheritance\ParentAImplementsB::class, 'getItems')];
        yield 'interface after a parent docblock without tag' => [$items, new \ReflectionMethod(PhpDocInheritance\DescribedParentAndInterface::class, 'getItems')];

        yield 'abstract trait method' => [$items, new \ReflectionMethod(PhpDocInheritance\ImplementsAbstractTrait::class, 'getItems')];
        yield 'private abstract trait method' => [$items, new \ReflectionMethod(PhpDocInheritance\ImplementsAbstractTrait::class, 'getHidden')];
        yield 'self in an abstract trait method' => [Type::list(Type::object(PhpDocInheritance\ImplementsAbstractTrait::class)), new \ReflectionMethod(PhpDocInheritance\ImplementsAbstractTrait::class, 'getSelves')];
        yield 'native self type of an abstract trait method' => [Type::object(PhpDocInheritance\ImplementsAbstractTrait::class), new \ReflectionMethod(PhpDocInheritance\ImplementsAbstractTrait::class, 'getNativeSelf')];
        yield 'concrete trait method' => [Type::array(), new \ReflectionMethod(PhpDocInheritance\OverridesConcreteTrait::class, 'getItems')];
        yield 'trait method overriding a parent method' => [$listOfA, new \ReflectionMethod(PhpDocInheritance\UndocumentedTraitWithParent::class, 'getItems')];
        yield 'trait method implementing an interface method' => [$items, new \ReflectionMethod(PhpDocInheritance\UndocumentedTraitWithInterface::class, 'getItems')];
        yield 'parent before abstract trait method' => [$listOfA, new \ReflectionMethod(PhpDocInheritance\ParentVsAbstractTrait::class, 'getItems')];
        yield 'interface before abstract trait method' => [$listOfB, new \ReflectionMethod(PhpDocInheritance\InterfaceVsAbstractTrait::class, 'getItems')];
        yield 'abstract trait method replaced by the parent method' => [Type::array(), new \ReflectionMethod(PhpDocInheritance\ParentKeptOverAbstractTrait::class, 'getItems')];
        yield 'abstract trait method after a docblock on the use statement' => [$items, new \ReflectionMethod(PhpDocInheritance\AfterUseDocBlock::class, 'getItems')];
        yield 'parent method declared by a trait' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildOfParentUsingDocumentedTrait::class, 'getItems')];

        yield 'class imported by the parent' => [Type::object(PhpDocInheritance\Model\Sub\Thing::class), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getThing')];
        yield 'class in the namespace of the parent' => [Type::object(PhpDocInheritance\Model\Widget::class), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getWidget')];
        yield 'self' => [Type::object(PhpDocInheritance\Model\ParentInModel::class), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getSelf')];
        yield 'static' => [Type::object(PhpDocInheritance\Entity\ChildInEntity::class), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getStatic')];
        yield '$this' => [Type::object(PhpDocInheritance\Entity\ChildInEntity::class), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getThis')];
        yield 'list of self' => [Type::list(Type::object(PhpDocInheritance\Model\ParentInModel::class)), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getSelfList')];
        yield 'parent' => [Type::object(PhpDocInheritance\Model\GrandParentInModel::class), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getParentObject')];
        yield 'type alias of the parent' => [$items, new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getAliased')];
        yield 'parent method declared by a trait in another namespace' => [Type::object(PhpDocInheritance\Model\Sub\Gadget::class), new \ReflectionMethod(PhpDocInheritance\Entity\ChildInEntity::class, 'getGadget')];

        yield 'template bound by the class' => [$items, new \ReflectionMethod(PhpDocInheritance\BoundChild::class, 'getItems')];
        yield 'template bound by an implementation' => [$items, new \ReflectionMethod(PhpDocInheritance\BoundImplementation::class, 'getItems')];
        yield 'unbound template' => [Type::list(Type::template('TItem')), new \ReflectionMethod(PhpDocInheritance\UnboundChild::class, 'getItems')];
        yield 'template bound by a parent for its trait' => [$items, new \ReflectionMethod(PhpDocInheritance\ChildOfParentUsingGenericTrait::class, 'getItems')];
        yield 'template bound through a parent' => [$items, new \ReflectionMethod(PhpDocInheritance\BoundLeaf::class, 'getItems')];

        yield 'promoted property of the parent' => [$items, new \ReflectionProperty(PhpDocInheritance\ChildWithoutParameter::class, 'items')];
        yield 'promoted property documented by the parent constructor' => [$items, new \ReflectionProperty(PhpDocInheritance\ChildPromotingOwn::class, 'items')];
        yield 'constructor parameter documented by the parent constructor' => [$items, new \ReflectionParameter([PhpDocInheritance\ChildPromotingOwn::class, '__construct'], 'items')];
        yield 'promoted property documented nowhere' => [Type::string(), new \ReflectionProperty(PhpDocInheritance\ChildPromotingOwn::class, 'name')];
        yield 'promoted property promoted again' => [$items, new \ReflectionProperty(PhpDocInheritance\ChildPromotingAgain::class, 'items')];
        yield 'property redeclaring a promoted property' => [$items, new \ReflectionProperty(PhpDocInheritance\ChildRedeclaringPromotedProperty::class, 'items')];
        yield 'promoted property declared by the parent' => [Type::array(), new \ReflectionProperty(PhpDocInheritance\ChildPromotingParentProperty::class, 'items')];
        yield 'constructor parameter' => [$items, new \ReflectionParameter([PhpDocInheritance\ChildPlain::class, '__construct'], 'items')];
        yield 'constructor parameter at another position' => [$items, new \ReflectionParameter([PhpDocInheritance\ChildReordered::class, '__construct'], 'items')];
        yield 'parameter of an abstract parent constructor' => [$items, new \ReflectionParameter([PhpDocInheritance\ImplementsAbstractConstructor::class, '__construct'], 'items')];
        yield 'parameter of an interface constructor' => [$items, new \ReflectionParameter([PhpDocInheritance\ImplementsConstructorInterface::class, '__construct'], 'items')];

        yield 'typed property' => [$items, new \ReflectionProperty(PhpDocInheritance\ChildWithProperties::class, 'typed')];
        yield 'untyped property' => [$items, new \ReflectionProperty(PhpDocInheritance\ChildWithProperties::class, 'untyped')];
        yield 'protected property made public' => [$items, new \ReflectionProperty(PhpDocInheritance\ChildWithProperties::class, 'widened')];
        yield 'private parent property' => [Type::array(), new \ReflectionProperty(PhpDocInheritance\ChildWithProperties::class, 'private')];
        yield 'trait property declared again' => [Type::array(), new \ReflectionProperty(PhpDocInheritance\ChildWithProperties::class, 'fromTrait')];
        yield 'interface property' => [$items, new \ReflectionProperty(PhpDocInheritance\ImplementsPropertyInterface::class, 'items')];
        yield 'abstract property' => [$items, new \ReflectionProperty(PhpDocInheritance\ImplementsAbstractProperty::class, 'items')];
        yield 'property after a docblock on the use statement' => [$items, new \ReflectionProperty(PhpDocInheritance\AfterUseDocBlockProperty::class, 'typed')];
    }

    public function testFallbackWhenNoPhpDoc()
    {
        $resolver = new PhpDocAwareReflectionTypeResolver(TypeResolver::create(), new StringTypeResolver(), new TypeContextFactory());
        $reflection = new \ReflectionClass(Dummy::class);

        $this->assertEquals(Type::int(), $resolver->resolve($reflection->getProperty('id')));
        $this->assertEquals(Type::int(), $resolver->resolve($reflection->getMethod('getId')));
        $this->assertEquals(Type::int(), $resolver->resolve($reflection->getMethod('setId')->getParameters()[0]));
    }
}
