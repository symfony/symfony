<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Loader\LoaderResolver;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AnnotationClassLoader loads routing information from a PHP class and its methods.
 *
 * You need to define an implementation for the getRouteDefaults() method. Most of the
 * time, this method should define some PHP callable to be called for the route
 * (a controller in MVC speak).
 *
 * The @Route annotation can be set on the class (for global parameters),
 * and on each method.
 *
 * The @Route annotation main value is the route pattern. The annotation also
 * recognizes three parameters: requirements, options, and name. The name parameter
 * is mandatory. Here is an example of how you should be able to use it:
 *
 *     /**
 *      * @Route("/Blog")
 *      * /
 *     class Blog
 *     {
 *         /**
 *          * @Route("/", name="blog_index")
 *          * /
 *         public function index()
 *         {
 *         }
 *
 *         /**
 *          * @Route("/:id", name="blog_post", requirements = {"id" = "\d+"})
 *          * /
 *         public function show()
 *         {
 *         }
 *     }
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class AnnotationClassLoader implements LoaderInterface
{
    protected $reader;

    /**
     * Constructor.
     *
     * @param AnnotationReader $reader
     */
    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Loads from annotations from a class.
     *
     * @param string $class A class name
     * @param string $type  The resource type
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When route can't be parsed
     */
    public function load($class, $type = null)
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        $class = new \ReflectionClass($class);
        $annotClass = 'Symfony\\Component\\Routing\\Annotation\\Route';

        $globals = array(
            'pattern'      => '',
            'requirements' => array(),
            'options'      => array(),
            'defaults'     => array(),
        );

        if ($annot = $this->reader->getClassAnnotation($class, $annotClass)) {
            if (null !== $annot->getPattern()) {
                $globals['pattern'] = $annot->getPattern();
            }

            if (null !== $annot->getRequirements()) {
                $globals['requirements'] = $annot->getRequirements();
            }

            if (null !== $annot->getOptions()) {
                $globals['options'] = $annot->getOptions();
            }

            if (null !== $annot->getDefaults()) {
                $globals['defaults'] = $annot->getDefaults();
            }
        }

        $this->reader->setDefaultAnnotationNamespace('Symfony\\Component\\Routing\\Annotation\\');
        $collection = new RouteCollection();
        foreach ($class->getMethods() as $method) {
            if ($annot = $this->reader->getMethodAnnotation($method, $annotClass)) {
                if (null === $annot->getName()) {
                    $annot->setName($this->getDefaultRouteName($class, $method));
                }

                $defaults = array_merge($globals['defaults'], $annot->getDefaults());
                $requirements = array_merge($globals['requirements'], $annot->getRequirements());
                $options = array_merge($globals['options'], $annot->getOptions());

                $route = new Route($globals['pattern'].$annot->getPattern(), $defaults, $requirements, $options);

                $this->configureRoute($route, $class, $method);

                $collection->add($annot->getName(), $route);
            }
        }

        return $collection;
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return boolean True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && preg_match('/^(?:\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+$/', $resource) && (!$type || 'annotation' === $type);
    }

    /**
     * Sets the loader resolver.
     *
     * @param LoaderResolver $resolver A LoaderResolver instance
     */
    public function setResolver(LoaderResolver $resolver)
    {
    }

    /**
     * Gets the loader resolver.
     *
     * @return LoaderResolver A LoaderResolver instance
     */
    public function getResolver()
    {
    }

    /**
     * Gets the default route name for a class method.
     *
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     *
     * @return string
     */
    protected function getDefaultRouteName(\ReflectionClass $class, \ReflectionMethod $method)
    {
        return strtolower(str_replace('\\', '_', $class->getName()).'_'.$method->getName());
    }

    abstract protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method);
}
