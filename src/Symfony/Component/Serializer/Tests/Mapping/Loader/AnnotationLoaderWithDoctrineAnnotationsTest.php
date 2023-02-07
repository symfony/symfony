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

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

class AnnotationLoaderWithDoctrineAnnotationsTest extends AnnotationLoaderTestCase
{
    protected function createLoader(): AnnotationLoader
    {
        return new AnnotationLoader(new AnnotationReader());
    }

    protected function getNamespace(): string
    {
        return 'Symfony\Component\Serializer\Tests\Fixtures\Annotations';
    }
}
