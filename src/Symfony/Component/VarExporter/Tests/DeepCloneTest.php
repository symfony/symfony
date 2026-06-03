<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\DeepCloner;
use Symfony\Component\VarExporter\Exception\ClassNotFoundException;
use Symfony\Component\VarExporter\Exception\LogicException;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Component\VarExporter\Tests\Fixtures\FooReadonly;
use Symfony\Component\VarExporter\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\VarExporter\Tests\Fixtures\GoodNight;
use Symfony\Component\VarExporter\Tests\Fixtures\MyWakeup;
use Symfony\Component\VarExporter\Tests\Fixtures\SimpleObject;

class DeepCloneTest extends TestCase
{
    public function testScalars()
    {
        $this->assertSame(42, DeepCloner::deepClone(42));
        $this->assertSame('hello', DeepCloner::deepClone('hello'));
        $this->assertSame(3.14, DeepCloner::deepClone(3.14));
        $this->assertTrue(DeepCloner::deepClone(true));
        $this->assertNull(DeepCloner::deepClone(null));
    }

    public function testSimpleArray()
    {
        $arr = ['a', 'b', [1, 2, 3]];
        $clone = DeepCloner::deepClone($arr);
        $this->assertSame($arr, $clone);
    }

    public function testUnitEnum()
    {
        $enum = FooUnitEnum::Bar;
        $this->assertSame($enum, DeepCloner::deepClone($enum));
    }

    public function testSimpleObject()
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $obj->baz = 123;

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        $this->assertEquals($obj, $clone);
        $this->assertSame('bar', $clone->foo);
        $this->assertSame(123, $clone->baz);
    }

    public function testNestedObjects()
    {
        $inner = new \stdClass();
        $inner->value = 'inner';

        $outer = new \stdClass();
        $outer->child = $inner;
        $outer->name = 'outer';

        $clone = DeepCloner::deepClone($outer);

        $this->assertNotSame($outer, $clone);
        $this->assertNotSame($inner, $clone->child);
        $this->assertSame('inner', $clone->child->value);
        $this->assertSame('outer', $clone->name);

        // Mutating original doesn't affect clone
        $inner->value = 'changed';
        $this->assertSame('inner', $clone->child->value);
    }

    public function testCircularReference()
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $a->ref = $b;
        $b->ref = $a;

        $clone = DeepCloner::deepClone($a);

        $this->assertNotSame($a, $clone);
        $this->assertNotSame($b, $clone->ref);
        $this->assertSame($clone, $clone->ref->ref);
    }

    public function testArrayWithObjects()
    {
        $obj = new \stdClass();
        $obj->x = 42;

        $arr = ['key' => $obj, 'str' => 'hello'];
        $clone = DeepCloner::deepClone($arr);

        $this->assertNotSame($obj, $clone['key']);
        $this->assertSame(42, $clone['key']->x);
        $this->assertSame('hello', $clone['str']);

        // Mutating original doesn't affect clone
        $obj->x = 99;
        $this->assertSame(42, $clone['key']->x);
    }

    public function testSameObjectMultipleReferences()
    {
        $shared = new \stdClass();
        $shared->val = 'shared';

        $root = new \stdClass();
        $root->a = $shared;
        $root->b = $shared;

        $clone = DeepCloner::deepClone($root);

        $this->assertNotSame($shared, $clone->a);
        $this->assertSame($clone->a, $clone->b);
    }

    public function testSleepWakeup()
    {
        $obj = new MyWakeup();
        $obj->sub = 123;
        $obj->bis = 'ignored_by_sleep';
        $obj->baz = 'baz_value';
        $obj->def = 456;

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        // __sleep returns ['sub', 'baz'], so 'bis' and 'def' should be reset
        $this->assertSame(123, $clone->sub);
        // __wakeup sets bis=123 and baz=123 when sub===123
        $this->assertSame(123, $clone->bis);
        $this->assertSame(123, $clone->baz);
        // def is not in __sleep, so it gets its default value 234
        $this->assertSame(234, $clone->def);
    }

    public function testSerializeUnserialize()
    {
        $obj = new class {
            public string $name = '';
            public array $data = [];

            public function __serialize(): array
            {
                return ['name' => $this->name, 'data' => $this->data];
            }

            public function __unserialize(array $data): void
            {
                $this->name = $data['name'];
                $this->data = $data['data'];
            }
        };

        $obj->name = 'test';
        $obj->data = ['a', 'b', 'c'];

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        $this->assertSame('test', $clone->name);
        $this->assertSame(['a', 'b', 'c'], $clone->data);
    }

    public function testReadonlyProperties()
    {
        $obj = new FooReadonly('hello', 'world');

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        $this->assertSame('hello', $clone->name);
        $this->assertSame('world', $clone->value);
    }

    public function testReadonlyPropertyContainingReferences()
    {
        $child = new \stdClass();
        $child->value = 'inner';

        $obj = new DeepCloneReadonlyReference(DeepCloneReadonlyReference::class, ['child' => $child]);

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        $this->assertSame($obj->class, $clone->class);
        $this->assertNotSame($child, $clone->data['child']);
        $this->assertSame('inner', $clone->data['child']->value);
    }

    public function testPrivateAndProtectedProperties()
    {
        $obj = new GoodNight();
        // __construct: unset($this->good), $this->foo='afternoon', $this->bar='morning'

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        $this->assertEquals($obj, $clone);
    }

    public function testDateTime()
    {
        $dt = new \DateTime('2024-01-15 10:30:00', new \DateTimeZone('UTC'));

        $clone = DeepCloner::deepClone($dt);

        $this->assertNotSame($dt, $clone);
        $this->assertEquals($dt, $clone);

        // Mutating original doesn't affect clone
        $dt->modify('+1 day');
        $this->assertNotEquals($dt, $clone);
    }

    public function testDateTimeImmutable()
    {
        $dt = new \DateTimeImmutable('2024-01-15 10:30:00', new \DateTimeZone('UTC'));

        $clone = DeepCloner::deepClone($dt);

        $this->assertNotSame($dt, $clone);
        $this->assertEquals($dt, $clone);
    }

    public function testObjectContainingDateTimeImmutable()
    {
        $obj = new DeepCloneDateTimeContainer(
            'test-item',
            new \DateTimeImmutable('2024-01-15 10:30:00', new \DateTimeZone('UTC'))
        );

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        $this->assertSame('test-item', $clone->name);
        $this->assertNotSame($obj->storedAt, $clone->storedAt);
        $this->assertEquals($obj->storedAt, $clone->storedAt);
    }

    public function testSplObjectStorage()
    {
        $s = new \SplObjectStorage();
        $o1 = new \stdClass();
        $o1->id = 1;
        $o2 = new \stdClass();
        $o2->id = 2;
        $s[$o1] = 'info1';
        $s[$o2] = 'info2';

        $clone = DeepCloner::deepClone($s);

        $this->assertNotSame($s, $clone);
        $this->assertCount(2, $clone);
    }

    public function testSplObjectStoragePreservesIdentityWithSharedKey()
    {
        // Same object referenced both as a key inside the storage AND outside it.
        // After deep clone, both references must point to the SAME cloned instance.
        $key = new \stdClass();
        $key->id = 'shared';

        $storage = new \SplObjectStorage();
        $storage[$key] = 'value';

        $graph = new \stdClass();
        $graph->storage = $storage;
        $graph->keyOutside = $key;

        $clone = DeepCloner::deepClone($graph);

        $this->assertNotSame($graph->keyOutside, $clone->keyOutside);
        $this->assertCount(1, $clone->storage);
        $clonedKey = null;
        foreach ($clone->storage as $k) {
            $clonedKey = $k;
        }
        $this->assertSame($clone->keyOutside, $clonedKey, 'Identity must be preserved between the storage key and the outside reference');
    }

    public function testSplObjectStoragePreservesIdentityWithSharedValue()
    {
        // Same object referenced both as a stored value AND outside the storage.
        $value = new \stdClass();
        $value->id = 'shared-value';

        $key = new \stdClass();
        $storage = new \SplObjectStorage();
        $storage[$key] = $value;

        $graph = new \stdClass();
        $graph->storage = $storage;
        $graph->valueOutside = $value;

        $clone = DeepCloner::deepClone($graph);

        $clonedKey = null;
        foreach ($clone->storage as $k) {
            $clonedKey = $k;
        }
        $this->assertSame($clone->valueOutside, $clone->storage[$clonedKey], 'Identity must be preserved between the stored value and the outside reference');
    }

    public function testArrayObjectPreservesIdentityWithSharedItem()
    {
        $shared = new \stdClass();
        $shared->id = 'shared';

        $ao = new \ArrayObject(['inside' => $shared]);

        $graph = new \stdClass();
        $graph->ao = $ao;
        $graph->outside = $shared;

        $clone = DeepCloner::deepClone($graph);

        $this->assertSame($clone->outside, $clone->ao['inside'], 'Identity must be preserved between an ArrayObject item and an outside reference');
    }

    public function testArrayIteratorPreservesIdentityWithSharedItem()
    {
        $shared = new \stdClass();
        $shared->id = 'shared';

        $ai = new \ArrayIterator(['inside' => $shared]);

        $graph = new \stdClass();
        $graph->ai = $ai;
        $graph->outside = $shared;

        $clone = DeepCloner::deepClone($graph);

        $this->assertSame($clone->outside, $clone->ai['inside'], 'Identity must be preserved between an ArrayIterator item and an outside reference');
    }

    public function testFromArrayThrowsClassNotFoundException()
    {
        $payload = [
            'classes' => 'NonExistentClassXyz',
            'objectMeta' => 1,
            'prepared' => 0,
            'properties' => ['NonExistentClassXyz' => ['foo' => ['bar']]],
        ];

        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Class "NonExistentClassXyz" not found');
        DeepCloner::fromArray($payload)->clone();
    }

    public static function provideMalformedFromArrayPayloads(): iterable
    {
        yield 'missing classes' => [['objectMeta' => 0, 'prepared' => 0], 'missing required "classes" key'];
        yield 'missing objectMeta' => [['classes' => 'stdClass', 'prepared' => 0], 'missing required "objectMeta" key'];
        yield 'missing prepared' => [['classes' => 'stdClass', 'objectMeta' => 0], 'missing required "prepared" key'];
        yield 'classes wrong type' => [['classes' => null, 'objectMeta' => 0, 'prepared' => 0], '"classes" must be of type string|array, null given'];
        yield 'objectMeta wrong type' => [['classes' => 'stdClass', 'objectMeta' => 'foo', 'prepared' => 0], '"objectMeta" must be of type int|array, string given'];
        yield 'objectMeta count negative' => [['classes' => 'stdClass', 'objectMeta' => -1, 'prepared' => 0], '"objectMeta" count must be non-negative'];
        yield 'objectMeta references empty classes' => [['classes' => '', 'objectMeta' => 1, 'prepared' => 0], 'references class index 0 but "classes" is empty'];
        yield 'cidx out of range' => [['classes' => 'stdClass', 'objectMeta' => [[5, 0]], 'prepared' => 0], 'out-of-range class index 5'];
        yield 'meta wrong shape' => [['classes' => 'stdClass', 'objectMeta' => [['x', 'y']], 'prepared' => 0], 'must be [int, int]'];
        yield 'meta wrong scalar' => [['classes' => 'stdClass', 'objectMeta' => ['foo'], 'prepared' => 0], 'must be of type int|array, string given'];
        yield 'states wrong type' => [['classes' => 'stdClass', 'objectMeta' => 0, 'prepared' => 0, 'states' => 'foo'], '"states" must be of type array, string given'];
    }

    #[DataProvider('provideMalformedFromArrayPayloads')]
    public function testFromArrayRejectsMalformedPayload(array $payload, string $expectedMessageFragment)
    {
        try {
            DeepCloner::fromArray($payload)->clone();
            $this->fail('Expected ValueError, none thrown');
        } catch (\ValueError $e) {
            $this->assertStringContainsString($expectedMessageFragment, $e->getMessage());
        }
    }

    public static function provideNonInstantiableClasses(): iterable
    {
        yield 'ReflectionClass' => [new \ReflectionClass(\stdClass::class), NotInstantiableTypeException::class];
        yield 'ReflectionMethod' => [new \ReflectionMethod(\ArrayObject::class, '__construct'), NotInstantiableTypeException::class];
        yield 'ReflectionProperty' => [new \ReflectionProperty(\Error::class, 'message'), NotInstantiableTypeException::class];
        yield 'IteratorIterator' => [new \IteratorIterator(new \ArrayIterator([1, 2])), NotInstantiableTypeException::class];
        // RecursiveIteratorIterator's newInstanceWithoutConstructor leaves the proto in a broken
        // state, so the throw happens earlier with a generic Error rather than the type check.
        yield 'RecursiveIteratorIterator' => [new \RecursiveIteratorIterator(new \RecursiveArrayIterator([[1]])), \Throwable::class];
        yield 'anonymous class' => [new class {
            public int $x = 1;
        }, NotInstantiableTypeException::class];
    }

    #[DataProvider('provideNonInstantiableClasses')]
    public function testDeepCloneRejectsNonInstantiableClass(object $value, string $expectedException)
    {
        $this->expectException($expectedException);
        DeepCloner::deepClone($value);
    }

    public function testDeepCloneMatchesSerializeUnserialize()
    {
        $inner = new \stdClass();
        $inner->value = str_repeat('x', 1000);

        $outer = new \stdClass();
        $outer->child = $inner;
        $outer->items = ['a', 'b', $inner];
        $outer->number = 42;

        $cloneA = unserialize(serialize($outer));
        $cloneB = DeepCloner::deepClone($outer);

        $this->assertEquals($cloneA, $cloneB);
    }

    public function testNamedClosure()
    {
        $fn = strlen(...);
        $clone = DeepCloner::deepClone($fn);

        $this->assertSame(5, $clone('hello'));
    }

    public function testRepeatedClones()
    {
        $obj = new \stdClass();
        $obj->value = 'original';

        $cloner = new DeepCloner($obj);

        $clone1 = $cloner->clone();
        $clone2 = $cloner->clone();

        $this->assertNotSame($obj, $clone1);
        $this->assertNotSame($obj, $clone2);
        $this->assertNotSame($clone1, $clone2);
        $this->assertEquals($obj, $clone1);
        $this->assertEquals($obj, $clone2);

        $clone1->value = 'changed';
        $this->assertSame('original', $clone2->value);
        $this->assertSame('original', $obj->value);
    }

    public function testRepeatedClonesWithNestedGraph()
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $a->ref = $b;
        $b->ref = $a;
        $a->data = str_repeat('x', 1000);

        $cloner = new DeepCloner($a);

        for ($i = 0; $i < 3; ++$i) {
            $clone = $cloner->clone();
            $this->assertNotSame($a, $clone);
            $this->assertSame($clone, $clone->ref->ref);
            $this->assertSame(str_repeat('x', 1000), $clone->data);
        }
    }

    public function testStaticValues()
    {
        $cloner = new DeepCloner(42);
        $this->assertSame(42, $cloner->clone());

        $cloner = new DeepCloner(['a', 'b']);
        $this->assertSame(['a', 'b'], $cloner->clone());
    }

    public function testCloneAs()
    {
        $obj = new FooReadonly('hello', 'world');

        $cloner = new DeepCloner($obj);
        $clone = $cloner->cloneAs(FooReadonly::class);

        $this->assertInstanceOf(FooReadonly::class, $clone);
        $this->assertNotSame($obj, $clone);
        $this->assertSame('hello', $clone->name);
        $this->assertSame('world', $clone->value);
    }

    public function testCloneAsRequiresObject()
    {
        $this->expectException(LogicException::class);
        $cloner = new DeepCloner([1, 2, new \stdClass()]);
        $cloner->cloneAs(\stdClass::class);
    }

    public function testOriginalMutationDoesNotAffectClone()
    {
        $obj = new \stdClass();
        $obj->foo = 'original';
        $obj->child = new \stdClass();
        $obj->child->bar = 'inner';

        $cloner = new DeepCloner($obj);

        // Mutate original after creating the cloner
        $obj->foo = 'mutated';
        $obj->child->bar = 'mutated-inner';

        $clone = $cloner->clone();
        $this->assertSame('original', $clone->foo);
        $this->assertSame('inner', $clone->child->bar);

        // Second clone should also be unaffected
        $clone2 = $cloner->clone();
        $this->assertSame('original', $clone2->foo);
        $this->assertSame('inner', $clone2->child->bar);
    }

    public function testSerializeClonerStaticValue()
    {
        $cloner = new DeepCloner(42);
        $restored = unserialize(serialize($cloner));

        $this->assertTrue($restored->isStaticValue());
        $this->assertSame(42, $restored->clone());
    }

    public function testSerializeClonerWithObjects()
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $obj->child = new \stdClass();
        $obj->child->val = 'inner';

        $cloner = new DeepCloner($obj);
        $data = serialize($cloner);

        // originals should not be in serialized form
        $this->assertStringNotContainsString('originals', $data);

        $restored = unserialize($data);
        $this->assertFalse($restored->isStaticValue());

        $clone = $restored->clone();
        $this->assertSame('bar', $clone->foo);
        $this->assertSame('inner', $clone->child->val);
        $this->assertNotSame($clone, $restored->clone());
    }

    public function testSerializeClonerStripsEmptyProperties()
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';

        $cloner = new DeepCloner($obj);
        $data = serialize($cloner);

        // No refs, no states, no resolve for this simple case - should be absent
        $this->assertStringNotContainsString('states', $data);
        $this->assertStringNotContainsString('refs', $data);
    }

    public function testSerializeClonerWithCircularRef()
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $a->ref = $b;
        $b->ref = $a;

        $cloner = new DeepCloner($a);
        $restored = unserialize(serialize($cloner));

        $clone = $restored->clone();
        $this->assertSame($clone, $clone->ref->ref);
    }

    public function testSerializeClonerWithNullByteKey()
    {
        $obj = new \stdClass();
        $obj->data = ["\0" => 'nul-key', 'normal' => new \stdClass()];
        $obj->data['normal']->x = 42;

        $cloner = new DeepCloner($obj);
        $restored = unserialize(serialize($cloner));

        $clone = $restored->clone();
        $this->assertSame('nul-key', $clone->data["\0"]);
        $this->assertSame(42, $clone->data['normal']->x);
        $this->assertNotSame($obj->data['normal'], $clone->data['normal']);
    }

    public function testIsStaticValue()
    {
        $this->assertTrue((new DeepCloner(42))->isStaticValue());
        $this->assertTrue((new DeepCloner('hello'))->isStaticValue());
        $this->assertTrue((new DeepCloner(null))->isStaticValue());
        $this->assertTrue((new DeepCloner(true))->isStaticValue());
        $this->assertTrue((new DeepCloner([1, 'a', [2]]))->isStaticValue());
        $this->assertTrue((new DeepCloner([]))->isStaticValue());
        $this->assertTrue((new DeepCloner(FooUnitEnum::Bar))->isStaticValue());

        $this->assertFalse((new DeepCloner(new \stdClass()))->isStaticValue());
        $this->assertFalse((new DeepCloner(['key' => new \stdClass()]))->isStaticValue());
    }

    public function testMixedSerializationClassesInSameIteration()
    {
        $obj = new DeepCloneWithMixedSerializationClasses(
            new DeepCloneSimpleRecord('test-value'),
            new \DateTimeImmutable('2024-01-15 10:30:00', new \DateTimeZone('UTC'))
        );

        $clone = DeepCloner::deepClone($obj);

        $this->assertNotSame($obj, $clone);
        $this->assertSame('test-value', $clone->record->value);
        $this->assertNotSame($obj->storedAt, $clone->storedAt);
        $this->assertEquals($obj->storedAt, $clone->storedAt);
    }

    public function testSerializeProducesPureArray()
    {
        // Simple objects
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $obj->child = new \stdClass();
        $obj->child->val = 'inner';
        self::assertPureArray((new DeepCloner($obj))->__serialize());

        // Circular references
        $a = new \stdClass();
        $b = new \stdClass();
        $a->ref = $b;
        $b->ref = $a;
        self::assertPureArray((new DeepCloner($a))->__serialize());

        // Named closure (global function)
        $fn = strlen(...);
        self::assertPureArray((new DeepCloner($fn))->__serialize());

        // Object with closure property
        $obj = new \stdClass();
        $obj->fn = strlen(...);
        self::assertPureArray((new DeepCloner($obj))->__serialize());

        // Hard references
        $value = [(object) []];
        $value[1] = &$value[0];
        $value[2] = $value[0];
        self::assertPureArray((new DeepCloner($value))->__serialize());

        // Array containing objects (objects nested inside array property)
        $inner = new \stdClass();
        $inner->value = 'x';
        $obj = new \stdClass();
        $obj->items = ['a', $inner, 'b'];
        self::assertPureArray((new DeepCloner($obj))->__serialize());
    }

    public function testSerializeRoundtripWithNamedClosure()
    {
        $fn = strlen(...);
        $cloner = new DeepCloner($fn);
        $restored = unserialize(serialize($cloner));
        $clone = $restored->clone();

        $this->assertSame(5, $clone('hello'));
    }

    public function testSerializeRoundtripWithClosureProperty()
    {
        $obj = new \stdClass();
        $obj->fn = strlen(...);
        $obj->name = 'test';

        $cloner = new DeepCloner($obj);
        $restored = unserialize(serialize($cloner));
        $clone = $restored->clone();

        $this->assertSame('test', $clone->name);
        $this->assertSame(5, ($clone->fn)('hello'));
    }

    public function testSerializeRoundtripWithHardRefs()
    {
        $value = [(object) []];
        $value[1] = &$value[0];
        $value[2] = $value[0];

        $cloner = new DeepCloner($value);
        $restored = unserialize(serialize($cloner));
        $clone = $restored->clone();

        $this->assertNotSame($value[0], $clone[0]);
        $this->assertEquals($value[0], $clone[0]);
        $this->assertSame($clone[0], $clone[2]);
    }

    public function testDeepCloneScalarHardRef()
    {
        $value = [1];
        $value[] = &$value[0];

        $clone = DeepCloner::deepClone($value);

        $this->assertSame(1, $clone[0]);
        $this->assertSame(1, $clone[1]);

        // Hard reference must be preserved: changing one changes the other
        $clone[0] = 999;
        $this->assertSame(999, $clone[1]);

        // Original must be unaffected
        $this->assertSame(1, $value[0]);
    }

    public function testDeepCloneArrayWithMultipleHardRefs()
    {
        $obj = new \stdClass();
        $obj->id = 42;
        $value = [$obj, 'hello'];
        $value[2] = &$value[0];
        $value[3] = &$value[1];

        $clone = DeepCloner::deepClone($value);

        // Object identity preserved across hard ref
        $this->assertNotSame($obj, $clone[0]);
        $this->assertSame($clone[0], $clone[2]);
        $this->assertSame(42, $clone[0]->id);

        // Scalar hard ref preserved
        $this->assertSame('hello', $clone[1]);
        $clone[1] = 'changed';
        $this->assertSame('changed', $clone[3]);

        // Original unaffected
        $this->assertSame('hello', $value[1]);
    }

    public function testConsecutiveClonesHaveIndependentHardRefs()
    {
        $value = [1];
        $value[] = &$value[0];

        $cloner = new DeepCloner($value);
        $clone1 = $cloner->clone();
        $clone2 = $cloner->clone();

        // Each clone has its own internal hard ref
        $clone1[0] = 999;
        $this->assertSame(999, $clone1[1]);

        // clone2 must be unaffected
        $this->assertSame(1, $clone2[0]);
        $this->assertSame(1, $clone2[1]);

        // Same via fromArray round-trip
        $restored = DeepCloner::fromArray($cloner->toArray());
        $clone3 = $restored->clone();
        $clone4 = $restored->clone();

        $clone3[0] = 888;
        $this->assertSame(888, $clone3[1]);
        $this->assertSame(1, $clone4[0]);
        $this->assertSame(1, $clone4[1]);
    }

    public function testUnsharedHardRefProducesStaticValue()
    {
        // An unshared external &-ref is unwrapped to a static value
        $x = [123];
        $cloner = new DeepCloner([&$x]);

        $this->assertTrue($cloner->isStaticValue());

        $clone1 = $cloner->clone();
        $clone2 = $cloner->clone();
        $clone1[0] = [999];
        $this->assertSame([123], $clone2[0]);
    }

    public function testDeepCloneEnumHardRef()
    {
        $x = FooUnitEnum::Bar;
        $value = [&$x, &$x];

        // toArray must produce a pure array (no enum objects)
        $cloner = new DeepCloner($value);
        self::assertPureArray($cloner->toArray());

        // Round-trip preserves the enum value and the & reference
        $clone = DeepCloner::fromArray($cloner->toArray())->clone();
        $this->assertSame(FooUnitEnum::Bar, $clone[0]);
        $this->assertSame(FooUnitEnum::Bar, $clone[1]);

        // Hard reference preserved: changing one changes the other
        $clone[0] = 'changed';
        $this->assertSame('changed', $clone[1]);
    }

    public function testFromArrayWithErrorScope()
    {
        // The Hydrator must accept both 'Error' and 'TypeError' as scope names
        $e = new \Error('test');
        $r = new \ReflectionProperty(\Error::class, 'trace');
        $r->setValue($e, ['file' => 'test.php', 'line' => 123]);
        $rl = new \ReflectionProperty(\Error::class, 'line');
        $rl->setValue($e, 234);

        $data = [
            'classes' => 'Error',
            'objectMeta' => [[0, 1]],
            'prepared' => 0,
            'properties' => [
                'Error' => [
                    'message' => ['test'],
                    'line' => [234],
                    'trace' => [['file' => 'test.php', 'line' => 123]],
                ],
            ],
            'states' => [1 => 0],
        ];
        $clone = DeepCloner::fromArray($data)->clone();
        $this->assertInstanceOf(\Error::class, $clone);
        $this->assertSame('test', $clone->getMessage());
        $this->assertSame(234, $clone->getLine());
        $this->assertSame(['file' => 'test.php', 'line' => 123], $clone->getTrace());

        $data2 = (new DeepCloner($e))->toArray();
        $clone2 = DeepCloner::fromArray($data2)->clone();
        $this->assertSame('test', $clone2->getMessage());
        $this->assertSame(['file' => 'test.php', 'line' => 123], $clone2->getTrace());
    }

    public function testFromArrayWithExceptionScope()
    {
        $data = [
            'classes' => 'Exception',
            'objectMeta' => [[0, 1]],
            'prepared' => 0,
            'properties' => [
                'Exception' => [
                    'message' => ['test'],
                    'trace' => [[]],
                ],
            ],
            'states' => [1 => 0],
        ];
        $clone = DeepCloner::fromArray($data)->clone();
        $this->assertInstanceOf(\Exception::class, $clone);
        $this->assertSame('test', $clone->getMessage());
    }

    public function testToArrayFromArrayScalarHardRef()
    {
        $value = [1];
        $value[] = &$value[0];

        $cloner = new DeepCloner($value);
        $restored = DeepCloner::fromArray($cloner->toArray());
        $clone = $restored->clone();

        $this->assertSame(1, $clone[0]);
        $clone[0] = 999;
        $this->assertSame(999, $clone[1]);
    }

    public function testToArrayFromArrayStaticValue()
    {
        $cloner = new DeepCloner(42);
        $restored = DeepCloner::fromArray($cloner->toArray());

        $this->assertTrue($restored->isStaticValue());
        $this->assertSame(42, $restored->clone());
    }

    public function testToArrayFromArrayWithObjects()
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $obj->child = new \stdClass();
        $obj->child->val = 'inner';

        $cloner = new DeepCloner($obj);
        $data = $cloner->toArray();
        self::assertPureArray($data);

        $restored = DeepCloner::fromArray($data);
        $clone = $restored->clone();

        $this->assertSame('bar', $clone->foo);
        $this->assertSame('inner', $clone->child->val);
        $this->assertNotSame($clone, $restored->clone());
    }

    public function testToArrayFromArrayWithNamedClosure()
    {
        $cloner = new DeepCloner(strlen(...));
        $data = $cloner->toArray();
        self::assertPureArray($data);

        $restored = DeepCloner::fromArray($data);
        $this->assertSame(5, $restored->clone()('hello'));
    }

    public function testToArrayFromArrayWithCircularRef()
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $a->ref = $b;
        $b->ref = $a;

        $cloner = new DeepCloner($a);
        $data = $cloner->toArray();
        self::assertPureArray($data);

        $restored = DeepCloner::fromArray($data);
        $clone = $restored->clone();

        $this->assertSame($clone, $clone->ref->ref);
    }

    // ── $allowedClasses tests ──

    public function testConstructorRejectsDisallowedClass()
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('"stdClass" is not allowed');
        new DeepCloner(new \stdClass(), []);
    }

    public function testCloneRejectsDisallowedClass()
    {
        $cloner = new DeepCloner(new \stdClass());
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('"stdClass" is not allowed');
        $cloner->clone([]);
    }

    public function testClonePermitsListedClass()
    {
        $cloner = new DeepCloner(new \stdClass());
        $clone = $cloner->clone(['stdClass']);
        $this->assertInstanceOf(\stdClass::class, $clone);
    }

    public function testDeepCloneWithAllowedClasses()
    {
        $o = new \stdClass();
        $o->x = 1;
        $clone = DeepCloner::deepClone($o, ['stdClass']);
        $this->assertSame(1, $clone->x);
    }

    public function testDeepCloneRejectsDisallowedClass()
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('"stdClass" is not allowed');
        DeepCloner::deepClone(new \stdClass(), ['DateTime']);
    }

    public function testDeepCloneOfLazyProxyMaterializesAndClones()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(SimpleObject::class, static function () use (&$initCounter) {
            ++$initCounter;

            return new SimpleObject();
        });

        $this->assertInstanceOf(LazyObjectInterface::class, $proxy);
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertSame(0, $initCounter);

        $clone = DeepCloner::deepClone($proxy);

        $this->assertSame(1, $initCounter, 'DeepCloner triggers initialization via __serialize().');
        $this->assertNotSame($proxy, $clone);
        $this->assertInstanceOf(SimpleObject::class, $clone);
        $this->assertSame('method', $clone->getMethod());

        $alreadyInitialized = $this->createLazyProxy(SimpleObject::class, static fn () => new SimpleObject());
        $this->assertSame('method', $alreadyInitialized->getMethod());
        $this->assertTrue($alreadyInitialized->isLazyObjectInitialized());

        $clone = DeepCloner::deepClone($alreadyInitialized);
        $this->assertInstanceOf(SimpleObject::class, $clone);
        $this->assertSame('method', $clone->getMethod());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function createLazyProxy(string $class, \Closure $initializer): object
    {
        $r = new \ReflectionClass($class);
        $proxyCode = ProxyHelper::generateLazyProxy($r);
        $proxyClass = str_replace('\\', '_', $class).'_DeepCloneLazy_'.md5($proxyCode);

        if (!class_exists($proxyClass, false)) {
            eval(($r->isReadOnly() ? 'readonly ' : '').'class '.$proxyClass.' '.$proxyCode);
        }

        return $proxyClass::createLazyProxy($initializer);
    }

    private static function assertPureArray(array $data, string $path = '', bool $root = true): void
    {
        foreach ($data as $key => $value) {
            $currentPath = $path ? $path.'.'.$key : (string) $key;
            if (\is_resource($value)) {
                self::fail(\sprintf('Found resource of type "%s" at path "%s" in serialized data.', get_resource_type($value), $currentPath));
            }
            if (\is_object($value)) {
                self::fail(\sprintf('Found object of class "%s" at path "%s" in serialized data.', $value::class, $currentPath));
            }
            if (\is_array($value)) {
                self::assertPureArray($value, $currentPath, false);
            }
        }
        if ($root) {
            self::assertTrue(true);
        }
    }
}

class DeepCloneDateTimeContainer
{
    public function __construct(
        public readonly string $name,
        public readonly \DateTimeImmutable $storedAt,
    ) {
    }
}

class DeepCloneReadonlyReference
{
    public function __construct(
        public readonly string $class,
        public readonly array $data,
    ) {
    }
}

class DeepCloneWithMixedSerializationClasses
{
    public function __construct(
        public readonly DeepCloneSimpleRecord $record,
        public readonly \DateTimeImmutable $storedAt,
    ) {
    }
}

class DeepCloneSimpleRecord
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
