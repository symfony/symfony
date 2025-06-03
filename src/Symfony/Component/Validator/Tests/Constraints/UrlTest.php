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
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class UrlTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $url = new Url(normalizer: 'trim', requireTld: true);

        $this->assertEquals('trim', $url->normalizer);
    }

    /**
     * @group legacy
     */
    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Url(['normalizer' => 'Unknown Callable', 'requireTld' => true]);
    }

    /**
     * @group legacy
     */
    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Url(['normalizer' => new \stdClass(), 'requireTld' => true]);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(UrlDummy::class);
        self::assertTrue((new AttributeLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(['http', 'https'], $aConstraint->protocols);
        self::assertFalse($aConstraint->relativeProtocol);
        self::assertNull($aConstraint->normalizer);
        self::assertFalse($aConstraint->requireTld);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(['ftp', 'gopher'], $bConstraint->protocols);
        self::assertSame('trim', $bConstraint->normalizer);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'UrlDummy'], $bConstraint->groups);
        self::assertFalse($bConstraint->requireTld);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertTrue($cConstraint->relativeProtocol);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
        self::assertFalse($cConstraint->requireTld);

        [$dConstraint] = $metadata->properties['d']->getConstraints();
        self::assertSame(['http', 'https'], $dConstraint->protocols);
        self::assertFalse($dConstraint->relativeProtocol);
        self::assertNull($dConstraint->normalizer);
        self::assertTrue($dConstraint->requireTld);
    }

    /**
     * @group legacy
     */
    public function testRequireTldDefaultsToFalse()
    {
        $constraint = new Url();

        $this->assertFalse($constraint->requireTld);
    }
}

class UrlDummy
{
    #[Url(requireTld: false)]
    private $a;

    #[Url(message: 'myMessage', protocols: ['ftp', 'gopher'], normalizer: 'trim', requireTld: false)]
    private $b;

    #[Url(relativeProtocol: true, groups: ['my_group'], payload: 'some attached data', requireTld: false)]
    private $c;

    #[Url(requireTld: true)]
    private $d;
}
