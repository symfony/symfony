<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Compiler;

use Symfony\Component\OpenApi\Configurator\DocumentationConfigurator;
use Symfony\Component\OpenApi\Documentation\DocumentationInterface;
use Symfony\Component\OpenApi\Loader\ComponentsLoaderInterface;
use Symfony\Component\OpenApi\Model\OpenApi;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class DocumentationCompiler implements DocumentationCompilerInterface
{
    /**
     * @param iterable<ComponentsLoaderInterface> $componentsLoaders
     */
    public function __construct(private iterable $componentsLoaders = [])
    {
    }

    public function compile(DocumentationInterface $doc): OpenApi
    {
        // Instanciate root configurator
        $rootConfigurator = new DocumentationConfigurator();

        // Load components for this doc
        foreach ($this->componentsLoaders as $loader) {
            $doc->loadComponents($rootConfigurator, $loader);
        }

        // Apply user documentation details
        $doc->configure($rootConfigurator);

        // Compile the documentation
        return $rootConfigurator->build($doc->getIdentifier(), $doc->getVersion());
    }
}
