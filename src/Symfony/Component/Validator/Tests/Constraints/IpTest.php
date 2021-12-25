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
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class IpTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $ip = new Ip(['normalizer' => 'trim']);

        $this->assertEquals('trim', $ip->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Ip(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Ip(['normalizer' => new \stdClass()]);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(IpDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(Ip::V4, $aConstraint->version);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(Ip::V6, $bConstraint->version);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame('trim', $bConstraint->normalizer);
        self::assertSame(['Default', 'IpDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class IpDummy
{
    #[Ip]
    private $a;

    #[Ip(version: Ip::V6, message: 'myMessage', normalizer: 'trim')]
    private $b;

    #[Ip(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
