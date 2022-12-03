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
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Serializer\Annotation\SerializedPath;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Tobias Bönner <tobi@boenner.family>
 */
class SerializedPathTest extends TestCase
{
    public function testEmptyStringSerializedPathParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedPath" must be a valid property path.');

        new SerializedPath('');
    }

    public function testSerializedPath()
    {
        $path = '[one][two]';
        $serializedPath = new SerializedPath($path);
        $propertyPath = new PropertyPath($path);
        $this->assertEquals($propertyPath, $serializedPath->getSerializedPath());
    }
}
