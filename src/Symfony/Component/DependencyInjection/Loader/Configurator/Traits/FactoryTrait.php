<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator\Traits;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\ExpressionLanguage\Expression;

trait FactoryTrait
{
    /**
     * Sets a factory.
     *
     * @return $this
     */
    final public function factory(string|array|ReferenceConfigurator|Expression $factory): static
    {
        if (\is_string($factory) && 1 === substr_count($factory, ':')) {
            $factoryParts = explode(':', $factory);

            throw new InvalidArgumentException(\sprintf('Invalid factory "%s": the "service:method" notation is not available when using PHP-based DI configuration. Use "[service(\'%s\'), \'%s\']" instead.', $factory, $factoryParts[0], $factoryParts[1]));
        }

        if ($factory instanceof Expression) {
            $factory = '@='.$factory;
        }

        $this->definition->setFactory(static::processValue($factory, true));

        return $this;
    }
}
