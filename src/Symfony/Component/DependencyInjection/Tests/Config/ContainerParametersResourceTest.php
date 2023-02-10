<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;

class ContainerParametersResourceTest extends TestCase
{
    /** @var ContainerParametersResource */
    private $resource;

    protected function setUp(): void
    {
        $this->resource = new ContainerParametersResource(['locales' => ['fr', 'en'], 'default_locale' => 'fr']);
    }

    public function testToString()
    {
        $this->assertSame('container_parameters_f2f012423c221eddf6c9a6305f965327', (string) $this->resource);
    }

    public function testSerializeUnserialize()
    {
        $unserialized = unserialize(serialize($this->resource));

        $this->assertEquals($this->resource, $unserialized);
    }

    public function testGetParameters()
    {
        $this->assertSame(['locales' => ['fr', 'en'], 'default_locale' => 'fr'], $this->resource->getParameters());
    }
}
