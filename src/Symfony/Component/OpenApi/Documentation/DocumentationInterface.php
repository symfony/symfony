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

use Symfony\Component\OpenApi\Configurator\DocumentationConfigurator;
use Symfony\Component\OpenApi\Loader\ComponentsLoaderInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
interface DocumentationInterface
{
    public function getIdentifier(): string;

    public function getVersion(): string;

    public function configure(DocumentationConfigurator $doc): void;

    public function loadComponents(DocumentationConfigurator $doc, ComponentsLoaderInterface $loader): void;
}
