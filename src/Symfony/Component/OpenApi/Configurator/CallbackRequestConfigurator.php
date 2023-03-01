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

use Symfony\Component\OpenApi\Model\CallbackRequest;
use Symfony\Component\OpenApi\Model\PathItem;
use Symfony\Component\OpenApi\Model\Reference;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class CallbackRequestConfigurator
{
    use Traits\ExtensionsTrait;

    private string $expression = '';
    private PathItem|Reference|null $definition = null;

    public function build(): CallbackRequest
    {
        return new CallbackRequest($this->expression, $this->definition, $this->specificationExtensions);
    }

    public function expression(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    public function definition(ReferenceConfigurator|PathItemConfigurator $definition): static
    {
        $this->definition = $definition->build();

        return $this;
    }
}
