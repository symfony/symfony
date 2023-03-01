<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator;

use Symfony\Component\OpenApi\Model\Server;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class ServerConfigurator
{
    use Traits\DescriptionTrait;
    use Traits\ExtensionsTrait;
    use Traits\ServerVariablesTrait;

    public function __construct(private readonly string $url)
    {
    }

    public function build(): Server
    {
        return new Server($this->url, $this->description, $this->variables ?: null, $this->specificationExtensions);
    }
}
