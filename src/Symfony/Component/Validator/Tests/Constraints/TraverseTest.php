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
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\TraversalStrategy;

class TraverseTest extends TestCase
{
    public function testPositiveAttributes()
    {
        $metadata = new ClassMetadata(TraverseDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));
        self::assertSame(TraversalStrategy::TRAVERSE, $metadata->getTraversalStrategy());
    }

    public function testNegativeAttribute()
    {
        $metadata = new ClassMetadata(DoNotTraverseMe::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));
        self::assertSame(TraversalStrategy::NONE, $metadata->getTraversalStrategy());
    }
}

#[Traverse]
class TraverseDummy
{
}

#[Traverse(false)]
class DoNotTraverseMe
{
}
