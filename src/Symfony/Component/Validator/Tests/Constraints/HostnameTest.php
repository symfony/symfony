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
use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class HostnameTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(HostnameDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertTrue($aConstraint->requireTld);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertFalse($bConstraint->requireTld);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'HostnameDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class HostnameDummy
{
    #[Hostname]
    private $a;

    #[Hostname(message: 'myMessage', requireTld: false)]
    private $b;

    #[Hostname(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
