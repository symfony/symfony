<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class TraceableAttributeClassLoader extends AttributeClassLoader
{
    /** @var list<string> */
    public array $foundClasses = [];

    public function load(mixed $class, string $type = null): RouteCollection
    {
        if (!is_string($class)) {
            throw new \InvalidArgumentException(sprintf('Expected string, got "%s"', get_debug_type($class)));
        }

        $this->foundClasses[] = $class;

        return parent::load($class, $type);
    }

    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot): void
    {
    }
}
