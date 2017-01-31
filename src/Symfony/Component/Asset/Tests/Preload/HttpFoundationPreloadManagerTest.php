<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Preload;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class HttpFoundationPreloadManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testManageResources()
    {
        $manager = new HttpFoundationPreloadManager();
        $this->assertInstanceOf(PreloadManagerInterface::class, $manager);

        $manager->setResources(array('/foo/bar.js' => array('as' => 'script', 'nopush' => false)));
        $manager->addResource('/foo/baz.css');
        $manager->addResource('/foo/bat.png', 'image', true);

        $this->assertEquals(array(
                '/foo/bar.js' => array('as' => 'script', 'nopush' => false),
                '/foo/baz.css' => array('as' => '', 'nopush' => false),
                '/foo/bat.png' => array('as' => 'image', 'nopush' => true),
        ), $manager->getResources());

        $response = new Response();
        $manager->setLinkHeader($response);

        $this->assertEquals('</foo/bar.js>; rel=preload; as=script,</foo/baz.css>; rel=preload,</foo/bat.png>; rel=preload; as=image; nopush', $response->headers->get('Link'));
    }

    /**
     * @expectedException \Symfony\Component\Asset\Exception\InvalidArgumentException
     * @dataProvider invalidResources
     */
    public function testInvalidResources($resources)
    {
        $manager = new HttpFoundationPreloadManager();
        $manager->setResources($resources);
    }

    public function invalidResources()
    {
        return array(
            array(array('foo' => array())),
            array(array('foo' => array('as'))),
            array(array('foo' => array('nopush'))),
        );
    }
}
