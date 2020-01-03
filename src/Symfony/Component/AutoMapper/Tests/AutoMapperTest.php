<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests;

use Symfony\Component\AutoMapper\AutoMapper;
use Symfony\Component\AutoMapper\Exception\CircularReferenceException;
use Symfony\Component\AutoMapper\Exception\NoMappingFoundException;
use Symfony\Component\AutoMapper\MapperContext;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;

/**
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class AutoMapperTest extends AutoMapperBaseTest
{
    public function testAutoMapping(): void
    {
        $userMetadata = $this->autoMapper->getMetadata(Fixtures\User::class, Fixtures\UserDTO::class);
        $userMetadata->forMember('yearOfBirth', function (Fixtures\User $user) {
            return ((int) date('Y')) - ((int) $user->age);
        });

        $address = new Fixtures\Address();
        $address->setCity('Toulon');
        $user = new Fixtures\User(1, 'yolo', '13');
        $user->address = $address;
        $user->addresses[] = $address;
        $user->money = 20.10;

        /** @var Fixtures\UserDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\UserDTO::class);

        self::assertInstanceOf(Fixtures\UserDTO::class, $userDto);
        self::assertSame(1, $userDto->id);
        self::assertSame('yolo', $userDto->getName());
        self::assertSame(13, $userDto->age);
        self::assertSame(((int) date('Y')) - 13, $userDto->yearOfBirth);
        self::assertCount(1, $userDto->addresses);
        self::assertInstanceOf(Fixtures\AddressDTO::class, $userDto->address);
        self::assertInstanceOf(Fixtures\AddressDTO::class, $userDto->addresses[0]);
        self::assertSame('Toulon', $userDto->address->city);
        self::assertSame('Toulon', $userDto->addresses[0]->city);
        self::assertIsArray($userDto->money);
        self::assertCount(1, $userDto->money);
        self::assertSame(20.10, $userDto->money[0]);
    }

    public function testAutoMapperFromArray(): void
    {
        $user = [
            'id' => 1,
            'address' => [
                'city' => 'Toulon',
            ],
            'createdAt' => '1987-04-30T06:00:00Z',
        ];

        /** @var Fixtures\UserDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\UserDTO::class);

        self::assertInstanceOf(Fixtures\UserDTO::class, $userDto);
        self::assertEquals(1, $userDto->id);
        self::assertInstanceOf(Fixtures\AddressDTO::class, $userDto->address);
        self::assertSame('Toulon', $userDto->address->city);
        self::assertInstanceOf(\DateTimeInterface::class, $userDto->createdAt);
        self::assertEquals(1987, $userDto->createdAt->format('Y'));
    }

    public function testAutoMapperToArray(): void
    {
        $address = new Fixtures\Address();
        $address->setCity('Toulon');
        $user = new Fixtures\User(1, 'yolo', '13');
        $user->address = $address;
        $user->addresses[] = $address;

        $userData = $this->autoMapper->map($user, 'array');

        self::assertIsArray($userData);
        self::assertEquals(1, $userData['id']);
        self::assertIsArray($userData['address']);
        self::assertIsString($userData['createdAt']);
    }

    public function testAutoMapperFromStdObject(): void
    {
        $user = new \stdClass();
        $user->id = 1;

        /** @var Fixtures\UserDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\UserDTO::class);

        self::assertInstanceOf(Fixtures\UserDTO::class, $userDto);
        self::assertEquals(1, $userDto->id);
    }

    public function testAutoMapperToStdObject(): void
    {
        $userDto = new Fixtures\UserDTO();
        $userDto->id = 1;

        $user = $this->autoMapper->map($userDto, \stdClass::class);

        self::assertInstanceOf(\stdClass::class, $user);
        self::assertEquals(1, $user->id);
    }

    public function testGroupsSourceTarget(): void
    {
        $foo = new Fixtures\Foo();
        $foo->setId(10);

        $bar = $this->autoMapper->map($foo, Fixtures\Bar::class, [MapperContext::GROUPS => ['group2']]);

        self::assertInstanceOf(Fixtures\Bar::class, $bar);
        self::assertEquals(10, $bar->getId());

        $bar = $this->autoMapper->map($foo, Fixtures\Bar::class, [MapperContext::GROUPS => ['group1', 'group3']]);

        self::assertInstanceOf(Fixtures\Bar::class, $bar);
        self::assertEquals(10, $bar->getId());

        $bar = $this->autoMapper->map($foo, Fixtures\Bar::class, [MapperContext::GROUPS => ['group1']]);

        self::assertInstanceOf(Fixtures\Bar::class, $bar);
        self::assertNull($bar->getId());

        $bar = $this->autoMapper->map($foo, Fixtures\Bar::class, [MapperContext::GROUPS => []]);

        self::assertInstanceOf(Fixtures\Bar::class, $bar);
        self::assertNull($bar->getId());

        $bar = $this->autoMapper->map($foo, Fixtures\Bar::class);

        self::assertInstanceOf(Fixtures\Bar::class, $bar);
        self::assertNull($bar->getId());
    }

    public function testGroupsToArray(): void
    {
        $foo = new Fixtures\Foo();
        $foo->setId(10);

        $fooArray = $this->autoMapper->map($foo, 'array', [MapperContext::GROUPS => ['group1']]);

        self::assertIsArray($fooArray);
        self::assertEquals(10, $fooArray['id']);

        $fooArray = $this->autoMapper->map($foo, 'array', [MapperContext::GROUPS => []]);

        self::assertIsArray($fooArray);
        self::assertArrayNotHasKey('id', $fooArray);

        $fooArray = $this->autoMapper->map($foo, 'array');

        self::assertIsArray($fooArray);
        self::assertArrayNotHasKey('id', $fooArray);
    }

    public function testDeepCloning(): void
    {
        $nodeA = new Fixtures\Node();
        $nodeB = new Fixtures\Node();
        $nodeB->parent = $nodeA;
        $nodeC = new Fixtures\Node();
        $nodeC->parent = $nodeB;
        $nodeA->parent = $nodeC;

        $newNode = $this->autoMapper->map($nodeA, Fixtures\Node::class);

        self::assertInstanceOf(Fixtures\Node::class, $newNode);
        self::assertNotSame($newNode, $nodeA);
        self::assertInstanceOf(Fixtures\Node::class, $newNode->parent);
        self::assertNotSame($newNode->parent, $nodeA->parent);
        self::assertInstanceOf(Fixtures\Node::class, $newNode->parent->parent);
        self::assertNotSame($newNode->parent->parent, $nodeA->parent->parent);
        self::assertInstanceOf(Fixtures\Node::class, $newNode->parent->parent->parent);
        self::assertSame($newNode, $newNode->parent->parent->parent);
    }

    public function testDeepCloningArray(): void
    {
        $nodeA = new Fixtures\Node();
        $nodeB = new Fixtures\Node();
        $nodeB->parent = $nodeA;
        $nodeC = new Fixtures\Node();
        $nodeC->parent = $nodeB;
        $nodeA->parent = $nodeC;

        $newNode = $this->autoMapper->map($nodeA, 'array');

        self::assertIsArray($newNode);
        self::assertIsArray($newNode['parent']);
        self::assertIsArray($newNode['parent']['parent']);
        self::assertIsArray($newNode['parent']['parent']['parent']);
        self::assertSame($newNode, $newNode['parent']['parent']['parent']);
    }

    public function testCircularReferenceArray(): void
    {
        $nodeA = new Fixtures\Node();
        $nodeB = new Fixtures\Node();

        $nodeA->childs[] = $nodeB;
        $nodeB->childs[] = $nodeA;

        $newNode = $this->autoMapper->map($nodeA, 'array');

        self::assertIsArray($newNode);
        self::assertIsArray($newNode['childs'][0]);
        self::assertIsArray($newNode['childs'][0]['childs'][0]);
        self::assertSame($newNode, $newNode['childs'][0]['childs'][0]);
    }

    public function testPrivate(): void
    {
        $user = new Fixtures\PrivateUser(10, 'foo', 'bar');
        /** @var Fixtures\PrivateUserDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\PrivateUserDTO::class);

        self::assertInstanceOf(Fixtures\PrivateUserDTO::class, $userDto);
        self::assertSame(10, $userDto->getId());
        self::assertSame('foo', $userDto->getFirstName());
        self::assertSame('bar', $userDto->getLastName());
    }

    public function testConstructor(): void
    {
        $autoMapper = AutoMapper::create(false, $this->loader);

        $user = new Fixtures\UserDTO();
        $user->id = 10;
        $user->setName('foo');
        $user->age = 3;
        /** @var Fixtures\UserConstructorDTO $userDto */
        $userDto = $autoMapper->map($user, Fixtures\UserConstructorDTO::class);

        self::assertInstanceOf(Fixtures\UserConstructorDTO::class, $userDto);
        self::assertSame('10', $userDto->getId());
        self::assertSame('foo', $userDto->getName());
        self::assertSame(3, $userDto->getAge());
    }

    public function testConstructorWithDefault(): void
    {
        $user = new Fixtures\UserDTONoAge();
        $user->id = 10;
        $user->name = 'foo';
        /** @var Fixtures\UserConstructorDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\UserConstructorDTO::class);

        self::assertInstanceOf(Fixtures\UserConstructorDTO::class, $userDto);
        self::assertSame('10', $userDto->getId());
        self::assertSame('foo', $userDto->getName());
        self::assertSame(30, $userDto->getAge());
    }

    public function testConstructorDisable(): void
    {
        $user = new Fixtures\UserDTONoName();
        $user->id = 10;
        /** @var Fixtures\UserConstructorDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\UserConstructorDTO::class);

        self::assertInstanceOf(Fixtures\UserConstructorDTO::class, $userDto);
        self::assertSame('10', $userDto->getId());
        self::assertNull($userDto->getName());
        self::assertNull($userDto->getAge());
    }

    public function testMaxDepth(): void
    {
        $foo = new Fixtures\FooMaxDepth(0, new Fixtures\FooMaxDepth(1, new Fixtures\FooMaxDepth(2, new Fixtures\FooMaxDepth(3, new Fixtures\FooMaxDepth(4)))));
        $fooArray = $this->autoMapper->map($foo, 'array');

        self::assertNotNull($fooArray['child']);
        self::assertNotNull($fooArray['child']['child']);
        self::assertFalse(isset($fooArray['child']['child']['child']));
    }

    public function testObjectToPopulate(): void
    {
        $configurationUser = $this->autoMapper->getMetadata(Fixtures\User::class, Fixtures\UserDTO::class);
        $configurationUser->forMember('yearOfBirth', function (Fixtures\User $user) {
            return ((int) date('Y')) - ((int) $user->age);
        });

        $user = new Fixtures\User(1, 'yolo', '13');
        $userDtoToPopulate = new Fixtures\UserDTO();

        $userDto = $this->autoMapper->map($user, Fixtures\UserDTO::class, [MapperContext::TARGET_TO_POPULATE => $userDtoToPopulate]);

        self::assertSame($userDtoToPopulate, $userDto);
    }

    public function testObjectToPopulateWithoutContext(): void
    {
        $configurationUser = $this->autoMapper->getMetadata(Fixtures\User::class, Fixtures\UserDTO::class);
        $configurationUser->forMember('yearOfBirth', function (Fixtures\User $user) {
            return ((int) date('Y')) - ((int) $user->age);
        });

        $user = new Fixtures\User(1, 'yolo', '13');
        $userDtoToPopulate = new Fixtures\UserDTO();

        $userDto = $this->autoMapper->map($user, $userDtoToPopulate);

        self::assertSame($userDtoToPopulate, $userDto);
    }

    public function testArrayToPopulate(): void
    {
        $configurationUser = $this->autoMapper->getMetadata(Fixtures\User::class, Fixtures\UserDTO::class);
        $configurationUser->forMember('yearOfBirth', function (Fixtures\User $user) {
            return ((int) date('Y')) - ((int) $user->age);
        });

        $user = new Fixtures\User(1, 'yolo', '13');
        $array = [];
        $arrayMapped = $this->autoMapper->map($user, $array);

        self::assertIsArray($arrayMapped);
        self::assertSame(1, $arrayMapped['id']);
        self::assertSame('yolo', $arrayMapped['name']);
        self::assertSame('13', $arrayMapped['age']);
    }

    public function testCircularReferenceLimitOnContext(): void
    {
        $nodeA = new Fixtures\Node();
        $nodeA->parent = $nodeA;

        $context = new MapperContext();
        $context->setCircularReferenceLimit(1);

        $this->expectException(CircularReferenceException::class);

        $this->autoMapper->map($nodeA, 'array', $context->toArray());
    }

    public function testCircularReferenceLimitOnMapper(): void
    {
        $nodeA = new Fixtures\Node();
        $nodeA->parent = $nodeA;

        $mapper = $this->autoMapper->getMapper(Fixtures\Node::class, 'array');
        $mapper->setCircularReferenceLimit(1);

        $this->expectException(CircularReferenceException::class);

        $mapper->map($nodeA);
    }

    public function testCircularReferenceHandlerOnContext(): void
    {
        $nodeA = new Fixtures\Node();
        $nodeA->parent = $nodeA;

        $context = new MapperContext();
        $context->setCircularReferenceHandler(function () {
            return 'foo';
        });

        $nodeArray = $this->autoMapper->map($nodeA, 'array', $context->toArray());

        self::assertSame('foo', $nodeArray['parent']);
    }

    public function testCircularReferenceHandlerOnMapper(): void
    {
        $nodeA = new Fixtures\Node();
        $nodeA->parent = $nodeA;

        $mapper = $this->autoMapper->getMapper(Fixtures\Node::class, 'array');
        $mapper->setCircularReferenceHandler(function () {
            return 'foo';
        });

        $nodeArray = $mapper->map($nodeA);

        self::assertSame('foo', $nodeArray['parent']);
    }

    public function testAllowedAttributes(): void
    {
        $configurationUser = $this->autoMapper->getMetadata(Fixtures\User::class, Fixtures\UserDTO::class);
        $configurationUser->forMember('yearOfBirth', function (Fixtures\User $user) {
            return ((int) date('Y')) - ((int) $user->age);
        });

        $user = new Fixtures\User(1, 'yolo', '13');

        $userDto = $this->autoMapper->map($user, Fixtures\UserDTO::class, [MapperContext::ALLOWED_ATTRIBUTES => ['id', 'age']]);

        self::assertNull($userDto->getName());
    }

    public function testIgnoredAttributes(): void
    {
        $configurationUser = $this->autoMapper->getMetadata(Fixtures\User::class, Fixtures\UserDTO::class);
        $configurationUser->forMember('yearOfBirth', function (Fixtures\User $user) {
            return ((int) date('Y')) - ((int) $user->age);
        });

        $user = new Fixtures\User(1, 'yolo', '13');
        $userDto = $this->autoMapper->map($user, Fixtures\UserDTO::class, [MapperContext::IGNORED_ATTRIBUTES => ['name']]);

        self::assertNull($userDto->getName());
    }

    public function testNameConverter(): void
    {
        $nameConverter = new class() implements AdvancedNameConverterInterface {
            public function normalize($propertyName, string $class = null, string $format = null, array $context = [])
            {
                if ('id' === $propertyName) {
                    return '@id';
                }

                return $propertyName;
            }

            public function denormalize($propertyName, string $class = null, string $format = null, array $context = [])
            {
                if ('@id' === $propertyName) {
                    return 'id';
                }

                return $propertyName;
            }
        };

        $autoMapper = AutoMapper::create(true, null, $nameConverter, 'Mapper2_');
        $user = new Fixtures\User(1, 'yolo', '13');

        $userArray = $autoMapper->map($user, 'array');

        self::assertIsArray($userArray);
        self::assertArrayHasKey('@id', $userArray);
        self::assertSame(1, $userArray['@id']);
    }

    public function testDefaultArguments(): void
    {
        $user = new Fixtures\UserDTONoAge();
        $user->id = 10;
        $user->name = 'foo';

        $context = new MapperContext();
        $context->setConstructorArgument(Fixtures\UserConstructorDTO::class, 'age', 50);

        /** @var Fixtures\UserConstructorDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\UserConstructorDTO::class, $context->toArray());

        self::assertInstanceOf(Fixtures\UserConstructorDTO::class, $userDto);
        self::assertSame(50, $userDto->getAge());
    }

    public function testDiscriminator(): void
    {
        $data = [
            'type' => 'cat',
        ];

        $pet = $this->autoMapper->map($data, Fixtures\Pet::class);

        self::assertInstanceOf(Fixtures\Cat::class, $pet);
    }

    public function testAutomapNull(): void
    {
        $array = $this->autoMapper->map(null, 'array');

        self::assertNull($array);
    }

    public function testInvalidMappingBothArray(): void
    {
        self::expectException(NoMappingFoundException::class);

        $data = ['test' => 'foo'];
        $array = $this->autoMapper->map($data, 'array');
    }

    public function testInvalidMappingSource(): void
    {
        self::expectException(NoMappingFoundException::class);

        $array = $this->autoMapper->map('test', 'array');
    }

    public function testInvalidMappingTarget(): void
    {
        self::expectException(NoMappingFoundException::class);

        $data = ['test' => 'foo'];
        $array = $this->autoMapper->map($data, 3);
    }

    public function testNoAutoRegister(): void
    {
        self::expectException(NoMappingFoundException::class);

        $automapper = AutoMapper::create(false, null, null, 'Mapper_', true, false);
        $automapper->getMapper(Fixtures\User::class, Fixtures\UserDTO::class);
    }
}
