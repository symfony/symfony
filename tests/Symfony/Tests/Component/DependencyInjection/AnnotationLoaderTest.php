<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabio B. Silva <fabio.bat.silva@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Container,
        Symfony\Component\DependencyInjection\Annotation\Autoware,
        Symfony\Component\DependencyInjection\AnnotationLoader,
        Doctrine\Common\Annotations\AnnotationReader;


require_once __DIR__.'/Fixtures/includes/annotatedclasses.php';

class AnnotationLoaderTest extends \PHPUnit_Framework_TestCase
{
    

    /**
     * @covers Symfony\Component\DependencyInjection\AnnotationLoader::load
     */
    public function testLoad()
    {
        $reader         = new AnnotationReader();
        $loader         = new AnnotationLoader($reader, 'FooAnnotatedClass');
        $annotations    = $loader->load();
        
        $this->assertEquals($annotations, $loader->getCollection(), '->getCollection() gets class annotations');
        
        $this->assertTrue($annotations->has("foo"));

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Annotation\Inject', $annotations->get("foo"), '->get() returns the service annotation');
    }

}