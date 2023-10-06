<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\MacAddress;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

/**
 * @author Ninos Ego <me@ninosego.de>
 */
class MacAddressTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $mac = new MacAddress(['normalizer' => 'trim']);

        $this->assertEquals('trim', $mac->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new MacAddress(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new MacAddress(['normalizer' => new \stdClass()]);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(MacAddressDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame('myMessage', $aConstraint->message);
        self::assertSame('trim', $aConstraint->normalizer);
        self::assertSame(['Default', 'MacAddressDummy'], $aConstraint->groups);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(['my_group'], $bConstraint->groups);
        self::assertSame('some attached data', $bConstraint->payload);
    }
}

class MacAddressDummy
{
    #[MacAddress(message: 'myMessage', normalizer: 'trim')]
    private $a;

    #[MacAddress(groups: ['my_group'], payload: 'some attached data')]
    private $b;
}
