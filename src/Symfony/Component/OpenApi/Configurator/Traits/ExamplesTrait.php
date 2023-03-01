<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator\Traits;

use Symfony\Component\OpenApi\Configurator\ExampleConfigurator;
use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Model\Example;
use Symfony\Component\OpenApi\Model\Reference;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait ExamplesTrait
{
    private mixed $example = null;

    /**
     * @var array<string, Example|Reference>|null
     */
    private ?array $examples = null;

    public function example(mixed $name, ExampleConfigurator|ReferenceConfigurator $example = null): static
    {
        if ($example) {
            $this->examples[$name] = $example->build();
        } else {
            $this->example = $name;
        }

        return $this;
    }
}
