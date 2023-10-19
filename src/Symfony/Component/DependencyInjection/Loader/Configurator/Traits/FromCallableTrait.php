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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\Configurator\FromCallableConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\ExpressionLanguage\Expression;

trait FromCallableTrait
{
    final public function fromCallable(string|array|ReferenceConfigurator|Expression $callable): FromCallableConfigurator
    {
        if ($this->definition instanceof ChildDefinition) {
            throw new InvalidArgumentException('The configuration key "parent" is unsupported when using "fromCallable()".');
        }

        foreach ([
            'synthetic' => 'isSynthetic',
            'factory' => 'getFactory',
            'file' => 'getFile',
            'arguments' => 'getArguments',
            'properties' => 'getProperties',
            'configurator' => 'getConfigurator',
            'calls' => 'getMethodCalls',
        ] as $key => $method) {
            if ($this->definition->$method()) {
                throw new InvalidArgumentException(sprintf('The configuration key "%s" is unsupported when using "fromCallable()".', $key));
            }
        }

        $this->definition->setFactory(['Closure', 'fromCallable']);

        if (\is_string($callable) && 1 === substr_count($callable, ':')) {
            $parts = explode(':', $callable);

            throw new InvalidArgumentException(sprintf('Invalid callable "%s": the "service:method" notation is not available when using PHP-based DI configuration. Use "[service(\'%s\'), \'%s\']" instead.', $callable, $parts[0], $parts[1]));
        }

        if ($callable instanceof Expression) {
            $callable = '@='.$callable;
        }

        $this->definition->setArguments([static::processValue($callable, true)]);

        if ('Closure' !== ($this->definition->getClass() ?? 'Closure')) {
            $this->definition->setLazy(true);
        } else {
            $this->definition->setClass('Closure');
        }

        return new FromCallableConfigurator($this, $this->definition);
    }
}
