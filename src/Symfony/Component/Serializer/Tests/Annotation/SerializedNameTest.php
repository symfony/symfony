<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
class SerializedNameTest extends TestCase
{
    public function testNotAStringSerializedNameParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedName" must be a non-empty string.');

        new SerializedName('');
    }

    public function testSerializedNameParameters()
    {
        $maxDepth = new SerializedName('foo');
        $this->assertEquals('foo', $maxDepth->getSerializedName());
    }
}
