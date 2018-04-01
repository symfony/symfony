<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Config\ContainerParametersResource;

class ContainerParametersResourceTest extends TestCase
{
    /** @var ContainerParametersResource */
    private $resource;

    protected function setUp()
    {
        $this->resource = new ContainerParametersResource(array('locales' => array('fr', 'en'), 'default_locale' => 'fr'));
    }

    public function testToString()
    {
        $this->assertSame('container_parameters_9893d3133814ab03cac3490f36dece77', (string) $this->resource);
    }

    public function testSerializeUnserialize()
    {
        $unserialized = unserialize(serialize($this->resource));

        $this->assertEquals($this->resource, $unserialized);
    }

    public function testGetParameters()
    {
        $this->assertSame(array('locales' => array('fr', 'en'), 'default_locale' => 'fr'), $this->resource->getParameters());
    }
}
