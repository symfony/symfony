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

use Symfony\Component\OpenApi\Configurator\CallbackRequestConfigurator;
use Symfony\Component\OpenApi\Configurator\PathItemConfigurator;
use Symfony\Component\OpenApi\Model\CallbackRequest;
use Symfony\Component\OpenApi\Model\PathItem;

class CallbackRequestConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testBuildEmpty(): void
    {
        $configurator = new CallbackRequestConfigurator();

        $callback = $configurator->build();
        $this->assertInstanceOf(CallbackRequest::class, $callback);
        $this->assertSame('', $callback->getExpression());
        $this->assertNull($callback->getDefinition());
        $this->assertSame([], $callback->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        [$pathItemConfigurator, $pathItem] = $this->createConfiguratorMock(PathItemConfigurator::class, PathItem::class);

        $configurator = (new CallbackRequestConfigurator())
            ->expression('{$request.query.queryUrl}')
            ->definition($pathItemConfigurator)
            ->specificationExtension('x-ext', 'value')
        ;

        $callback = $configurator->build();
        $this->assertInstanceOf(CallbackRequest::class, $callback);
        $this->assertSame('{$request.query.queryUrl}', $callback->getExpression());
        $this->assertSame($pathItem, $callback->getDefinition());
        $this->assertSame(['x-ext' => 'value'], $callback->getSpecificationExtensions());
    }
}
