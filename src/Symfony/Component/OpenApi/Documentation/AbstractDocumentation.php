<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Documentation;

use Symfony\Component\OpenApi\Builder\OpenApiBuilder;
use Symfony\Component\OpenApi\Builder\OpenApiBuilderInterface;
use Symfony\Component\OpenApi\Configurator\DocumentationConfigurator;
use Symfony\Component\OpenApi\Loader\ComponentsLoaderInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
abstract class AbstractDocumentation implements DocumentationInterface
{
    protected function getOpenApiBuilder(): OpenApiBuilderInterface
    {
        return new OpenApiBuilder();
    }

    public function getIdentifier(): string
    {
        return 'api';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function loadComponents(DocumentationConfigurator $doc, ComponentsLoaderInterface $loader): void
    {
        $doc->components($loader->load($this->getOpenApiBuilder()));
    }
}
