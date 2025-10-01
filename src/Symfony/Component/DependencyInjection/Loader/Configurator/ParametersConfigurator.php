<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ParametersConfigurator extends AbstractConfigurator
{
    public const FACTORY = 'parameters';

    public function __construct(
        private ContainerBuilder $container,
    ) {
    }

    /**
     * @return $this
     */
    final public function set(string $name, mixed $value): static
    {
        if ($value instanceof Expression) {
            throw new InvalidArgumentException(\sprintf('Using an expression in parameter "%s" is not allowed.', $name));
        }

        $this->container->setParameter($name, static::processValue($value, true));

        return $this;
    }

    /**
     * @return $this
     */
    final public function __invoke(string $name, mixed $value): static
    {
        return $this->set($name, $value);
    }
}
