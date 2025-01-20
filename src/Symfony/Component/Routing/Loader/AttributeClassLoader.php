<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * AttributeClassLoader loads routing information from a PHP class and its methods.
 *
 * You need to define an implementation for the configureRoute() method. Most of the
 * time, this method should define some PHP callable to be called for the route
 * (a controller in MVC speak).
 *
 * The #[Route] attribute can be set on the class (for global parameters),
 * and on each method.
 *
 * The #[Route] attribute main value is the route path. The attribute also
 * recognizes several parameters: requirements, options, defaults, schemes,
 * methods, host, and name. The name parameter is mandatory.
 * Here is an example of how you should be able to use it:
 *
 *     #[Route('/Blog')]
 *     class Blog
 *     {
 *         #[Route('/', name: 'blog_index')]
 *         public function index()
 *         {
 *         }
 *         #[Route('/{id}', name: 'blog_post', requirements: ["id" => '\d+'])]
 *         public function show()
 *         {
 *         }
 *     }
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
abstract class AttributeClassLoader implements LoaderInterface
{
    /**
     * @deprecated since Symfony 7.2, use "setRouteAttributeClass()" instead.
     */
    protected string $routeAnnotationClass = RouteAttribute::class;
    private string $routeAttributeClass = RouteAttribute::class;
    protected int $defaultRouteIndex = 0;

    public function __construct(
        protected readonly ?string $env = null,
    ) {
    }

    /**
     * @deprecated since Symfony 7.2, use "setRouteAttributeClass(string $class)" instead
     *
     * Sets the annotation class to read route properties from.
     */
    public function setRouteAnnotationClass(string $class): void
    {
        trigger_deprecation('symfony/routing', '7.2', 'The "%s()" method is deprecated, use "%s::setRouteAttributeClass()" instead.', __METHOD__, self::class);

        $this->setRouteAttributeClass($class);
    }

    /**
     * Sets the attribute class to read route properties from.
     */
    public function setRouteAttributeClass(string $class): void
    {
        $this->routeAnnotationClass = $class;
        $this->routeAttributeClass = $class;
    }

    /**
     * @throws \InvalidArgumentException When route can't be parsed
     */
    public function load(mixed $class, ?string $type = null): RouteCollection
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist.', $class));
        }

        $class = new \ReflectionClass($class);
        if ($class->isAbstract()) {
            throw new \InvalidArgumentException(\sprintf('Attributes from class "%s" cannot be read as it is abstract.', $class->getName()));
        }

        $globals = $this->getGlobals($class);
        $collection = new RouteCollection();
        $collection->addResource(new FileResource($class->getFileName()));
        if ($globals['env'] && $this->env !== $globals['env']) {
            return $collection;
        }
        $fqcnAlias = false;
        foreach ($class->getMethods() as $method) {
            $this->defaultRouteIndex = 0;
            $routeNamesBefore = array_keys($collection->all());
            foreach ($this->getAttributes($method) as $attr) {
                $this->addRoute($collection, $attr, $globals, $class, $method);
                if ('__invoke' === $method->name) {
                    $fqcnAlias = true;
                }
            }

            if (1 === $collection->count() - \count($routeNamesBefore)) {
                $newRouteName = current(array_diff(array_keys($collection->all()), $routeNamesBefore));
                if ($newRouteName !== $aliasName = \sprintf('%s::%s', $class->name, $method->name)) {
                    $collection->addAlias($aliasName, $newRouteName);
                }
            }
        }
        if (0 === $collection->count() && $class->hasMethod('__invoke')) {
            $globals = $this->resetGlobals();
            foreach ($this->getAttributes($class) as $attr) {
                $this->addRoute($collection, $attr, $globals, $class, $class->getMethod('__invoke'));
                $fqcnAlias = true;
            }
        }
        if ($fqcnAlias && 1 === $collection->count()) {
            $invokeRouteName = key($collection->all());
            if ($invokeRouteName !== $class->name) {
                $collection->addAlias($class->name, $invokeRouteName);
            }

            if ($invokeRouteName !== $aliasName = \sprintf('%s::__invoke', $class->name)) {
                $collection->addAlias($aliasName, $invokeRouteName);
            }
        }

        return $collection;
    }

    /**
     * @param RouteAttribute $attr or an object that exposes a similar interface
     */
    protected function addRoute(RouteCollection $collection, object $attr, array $globals, \ReflectionClass $class, \ReflectionMethod $method): void
    {
        if ($attr->getEnv() && $attr->getEnv() !== $this->env) {
            return;
        }

        $name = $attr->getName() ?? $this->getDefaultRouteName($class, $method);
        $name = $globals['name'].$name;

        $requirements = $attr->getRequirements();

        foreach ($requirements as $placeholder => $requirement) {
            if (\is_int($placeholder)) {
                throw new \InvalidArgumentException(\sprintf('A placeholder name must be a string (%d given). Did you forget to specify the placeholder key for the requirement "%s" of route "%s" in "%s::%s()"?', $placeholder, $requirement, $name, $class->getName(), $method->getName()));
            }
        }

        $defaults = array_replace($globals['defaults'], $attr->getDefaults());
        $requirements = array_replace($globals['requirements'], $requirements);
        $options = array_replace($globals['options'], $attr->getOptions());
        $schemes = array_unique(array_merge($globals['schemes'], $attr->getSchemes()));
        $methods = array_unique(array_merge($globals['methods'], $attr->getMethods()));

        $host = $attr->getHost() ?? $globals['host'];
        $condition = $attr->getCondition() ?? $globals['condition'];
        $priority = $attr->getPriority() ?? $globals['priority'];

        $path = $attr->getLocalizedPaths() ?: $attr->getPath();
        $prefix = $globals['localized_paths'] ?: $globals['path'];
        $paths = [];

        if (\is_array($path)) {
            if (!\is_array($prefix)) {
                foreach ($path as $locale => $localePath) {
                    $paths[$locale] = $prefix.$localePath;
                }
            } elseif ($missing = array_diff_key($prefix, $path)) {
                throw new \LogicException(\sprintf('Route to "%s" is missing paths for locale(s) "%s".', $class->name.'::'.$method->name, implode('", "', array_keys($missing))));
            } else {
                foreach ($path as $locale => $localePath) {
                    if (!isset($prefix[$locale])) {
                        throw new \LogicException(\sprintf('Route to "%s" with locale "%s" is missing a corresponding prefix in class "%s".', $method->name, $locale, $class->name));
                    }

                    $paths[$locale] = $prefix[$locale].$localePath;
                }
            }
        } elseif (\is_array($prefix)) {
            foreach ($prefix as $locale => $localePrefix) {
                $paths[$locale] = $localePrefix.$path;
            }
        } else {
            $paths[] = $prefix.$path;
        }

        foreach ($method->getParameters() as $param) {
            if (isset($defaults[$param->name]) || !$param->isDefaultValueAvailable()) {
                continue;
            }
            foreach ($paths as $locale => $path) {
                if (preg_match(\sprintf('/\{%s(?:<.*?>)?\}/', preg_quote($param->name)), $path)) {
                    if (\is_scalar($defaultValue = $param->getDefaultValue()) || null === $defaultValue) {
                        $defaults[$param->name] = $defaultValue;
                    } elseif ($defaultValue instanceof \BackedEnum) {
                        $defaults[$param->name] = $defaultValue->value;
                    }
                    break;
                }
            }
        }

        foreach ($paths as $locale => $path) {
            $route = $this->createRoute($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
            $this->configureRoute($route, $class, $method, $attr);
            if (0 !== $locale) {
                $route->setDefault('_locale', $locale);
                $route->setRequirement('_locale', preg_quote($locale));
                $route->setDefault('_canonical_route', $name);
                $collection->add($name.'.'.$locale, $route, $priority);
            } else {
                $collection->add($name, $route, $priority);
            }
        }
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return \is_string($resource) && preg_match('/^(?:\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+$/', $resource) && (!$type || 'attribute' === $type);
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
    }

    public function getResolver(): LoaderResolverInterface
    {
        throw new LogicException(\sprintf('The "%s()" method must not be called.', __METHOD__));
    }

    /**
     * Gets the default route name for a class method.
     *
     * @return string
     */
    protected function getDefaultRouteName(\ReflectionClass $class, \ReflectionMethod $method)
    {
        $name = str_replace('\\', '_', $class->name).'_'.$method->name;
        $name = \function_exists('mb_strtolower') && preg_match('//u', $name) ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        if ($this->defaultRouteIndex > 0) {
            $name .= '_'.$this->defaultRouteIndex;
        }
        ++$this->defaultRouteIndex;

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getGlobals(\ReflectionClass $class): array
    {
        $globals = $this->resetGlobals();

        // to be replaced in Symfony 8.0 by $this->routeAttributeClass
        if ($attribute = $class->getAttributes($this->routeAnnotationClass, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null) {
            $attr = $attribute->newInstance();

            if (null !== $attr->getName()) {
                $globals['name'] = $attr->getName();
            }

            if (null !== $attr->getPath()) {
                $globals['path'] = $attr->getPath();
            }

            $globals['localized_paths'] = $attr->getLocalizedPaths();

            if (null !== $attr->getRequirements()) {
                $globals['requirements'] = $attr->getRequirements();
            }

            if (null !== $attr->getOptions()) {
                $globals['options'] = $attr->getOptions();
            }

            if (null !== $attr->getDefaults()) {
                $globals['defaults'] = $attr->getDefaults();
            }

            if (null !== $attr->getSchemes()) {
                $globals['schemes'] = $attr->getSchemes();
            }

            if (null !== $attr->getMethods()) {
                $globals['methods'] = $attr->getMethods();
            }

            if (null !== $attr->getHost()) {
                $globals['host'] = $attr->getHost();
            }

            if (null !== $attr->getCondition()) {
                $globals['condition'] = $attr->getCondition();
            }

            $globals['priority'] = $attr->getPriority() ?? 0;
            $globals['env'] = $attr->getEnv();

            foreach ($globals['requirements'] as $placeholder => $requirement) {
                if (\is_int($placeholder)) {
                    throw new \InvalidArgumentException(\sprintf('A placeholder name must be a string (%d given). Did you forget to specify the placeholder key for the requirement "%s" in "%s"?', $placeholder, $requirement, $class->getName()));
                }
            }
        }

        return $globals;
    }

    private function resetGlobals(): array
    {
        return [
            'path' => null,
            'localized_paths' => [],
            'requirements' => [],
            'options' => [],
            'defaults' => [],
            'schemes' => [],
            'methods' => [],
            'host' => '',
            'condition' => '',
            'name' => '',
            'priority' => 0,
            'env' => null,
        ];
    }

    protected function createRoute(string $path, array $defaults, array $requirements, array $options, ?string $host, array $schemes, array $methods, ?string $condition): Route
    {
        return new Route($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
    }

    /**
     * @param RouteAttribute $attr or an object that exposes a similar interface
     *
     * @return void
     */
    abstract protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr);

    /**
     * @return iterable<int, RouteAttribute>
     */
    private function getAttributes(\ReflectionClass|\ReflectionMethod $reflection): iterable
    {
        // to be replaced in Symfony 8.0 by $this->routeAttributeClass
        foreach ($reflection->getAttributes($this->routeAnnotationClass, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            yield $attribute->newInstance();
        }
    }
}
