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

use Doctrine\Common\Annotations\AnnotationReader;
use PhpParser\ParserFactory;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\AutoMapper\AutoMapper;
use Symfony\Component\AutoMapper\Generator\Generator;
use Symfony\Component\AutoMapper\Loader\FileLoader;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

/**
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class AutoMapperTest extends TestCase
{
    /** @var AutoMapper */
    private $autoMapper;

    public function setUp()
    {
        @unlink(__DIR__.'/cache/registry.php');
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $loader = new FileLoader(new Generator(
            (new ParserFactory())->create(ParserFactory::PREFER_PHP7),
            new ClassDiscriminatorFromClassMetadata($classMetadataFactory)
        ), __DIR__.'/cache');

        $this->autoMapper = AutoMapper::create(true, $loader);
    }

    public function testAutoMapping()
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

        /** @var Fixtures\UserDTO $userDto */
        $userDto = $this->autoMapper->map($user, Fixtures\UserDTO::class);

        self::assertInstanceOf(Fixtures\UserDTO::class, $userDto);
        self::assertSame(1, $userDto->id);
        self::assertSame('yolo', $userDto->name);
        self::assertSame(13, $userDto->age);
        self::assertSame(((int) date('Y')) - 13, $userDto->yearOfBirth);
        self::assertCount(1, $userDto->addresses);
        self::assertInstanceOf(Fixtures\AddressDTO::class, $userDto->address);
        self::assertInstanceOf(Fixtures\AddressDTO::class, $userDto->addresses[0]);
        self::assertSame('Toulon', $userDto->address->city);
        self::assertSame('Toulon', $userDto->addresses[0]->city);
    }
}
