<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Tests\Configurator;

use Symfony\Component\OpenApi\Configurator\LinkConfigurator;
use Symfony\Component\OpenApi\Configurator\ParameterConfigurator;
use Symfony\Component\OpenApi\Configurator\ServerConfigurator;
use Symfony\Component\OpenApi\Model\Link;
use Symfony\Component\OpenApi\Model\Parameter;
use Symfony\Component\OpenApi\Model\Server;

class LinkConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testBuildEmpty(): void
    {
        $configurator = new LinkConfigurator();

        $link = $configurator->build();
        $this->assertInstanceOf(Link::class, $link);
        $this->assertNull($link->getOperationRef());
        $this->assertNull($link->getOperationId());
        $this->assertNull($link->getParameters());
        $this->assertNull($link->getRequestBody());
        $this->assertNull($link->getDescription());
        $this->assertNull($link->getServer());
        $this->assertSame([], $link->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        [$parameterConfigurator, $parameter] = $this->createConfiguratorMock(ParameterConfigurator::class, Parameter::class);
        [$serverConfigurator, $server] = $this->createConfiguratorMock(ServerConfigurator::class, Server::class);

        $configurator = (new LinkConfigurator())
            ->operationRef('reference')
            ->operationId('id')
            ->parameter($parameterConfigurator)
            ->requestBody('request body')
            ->description('description')
            ->server($serverConfigurator)
            ->specificationExtension('x-ext', 'value')
        ;

        $link = $configurator->build();
        $this->assertInstanceOf(Link::class, $link);
        $this->assertSame('reference', $link->getOperationRef());
        $this->assertSame('id', $link->getOperationId());
        $this->assertSame($parameter, $link->getParameters()[0]);
        $this->assertSame('request body', $link->getRequestBody());
        $this->assertSame('description', $link->getDescription());
        $this->assertSame($server, $link->getServer());
        $this->assertSame(['x-ext' => 'value'], $link->getSpecificationExtensions());
    }
}
