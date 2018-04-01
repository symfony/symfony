<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection;

use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class AnnotationReaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // simulate using "annotation_reader" in a compiler pass
        $container->get('test.annotation_reader');
    }
}
