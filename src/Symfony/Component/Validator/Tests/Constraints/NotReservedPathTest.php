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
use Symfony\Component\Validator\Constraints\NotReservedPath;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class NotReservedPathTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(NotReservedPathDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame('myMessage', $aConstraint->message);
    }
}

class NotReservedPathDummy
{
    #[NotReservedPath(message: 'myMessage')]
    private $a = 'toto';
}
