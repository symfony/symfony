<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveBuildParametersPass implements CompilerPassInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $removedParameters = [];

    public function __construct(
        private bool $preserveArrays = false,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $parameterBag = $container->getParameterBag();
        $this->removedParameters = [];

        foreach ($parameterBag->all() as $name => $value) {
            if ('.' === ($name[0] ?? '') && (!$this->preserveArrays || !\is_array($value))) {
                $this->removedParameters[$name] = $value;

                $parameterBag->remove($name);
                $container->log($this, \sprintf('Removing build parameter "%s".', $name));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getRemovedParameters(): array
    {
        return $this->removedParameters;
    }
}
