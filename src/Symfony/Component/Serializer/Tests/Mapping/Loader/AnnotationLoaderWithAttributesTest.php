<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Loader;

use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

class AnnotationLoaderWithAttributesTest extends AnnotationLoaderTestCase
{
    protected function createLoader(): AnnotationLoader
    {
        return new AnnotationLoader();
    }

    protected function getNamespace(): string
    {
        return 'Symfony\Component\Serializer\Tests\Fixtures\Attributes';
    }

    public function testLoadWithInvalidAttribute()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Could not instantiate attribute "Symfony\Component\Serializer\Annotation\Groups" on "Symfony\Component\Serializer\Tests\Fixtures\Attributes\BadAttributeDummy::myMethod()".');

        $classMetadata = new ClassMetadata($this->getNamespace().'\BadAttributeDummy');

        $this->loader->loadClassMetadata($classMetadata);
    }
}
