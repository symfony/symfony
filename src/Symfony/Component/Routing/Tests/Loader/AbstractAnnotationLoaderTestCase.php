<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;

abstract class AbstractAnnotationLoaderTestCase extends TestCase
{
    public function getReader()
    {
        return $this->getMockBuilder(\Doctrine\Common\Annotations\Reader::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    public function getClassLoader($reader)
    {
        return $this->getMockBuilder(AnnotationClassLoader::class)
            ->setConstructorArgs([$reader])
            ->getMockForAbstractClass()
        ;
    }
}
