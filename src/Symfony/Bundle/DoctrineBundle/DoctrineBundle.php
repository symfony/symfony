<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    public function boot()
    {
        // force Doctrine annotations to be loaded
        // should be removed when a better solution is found in Doctrine
        class_exists('Doctrine\ORM\Mapping\Driver\AnnotationDriver');

        // Register an autoloader for proxies to avoid issues when unserializing them
        // when the ORM is used.
        if ($this->container->hasParameter('doctrine.orm.proxy_namespace')) {
            $namespace = $this->container->getParameter('doctrine.orm.proxy_namespace');
            $dir = $this->container->getParameter('doctrine.orm.proxy_dir');
            $container = $this->container;

            spl_autoload_register(function($class) use ($namespace, $dir, $container) {
                if (0 === strpos($class, $namespace)) {
                    $className = substr($class, strlen($namespace) +1);
                    $file = $dir.DIRECTORY_SEPARATOR.$className.'.php';

                    if (!file_exists($file) && $container->getParameter('kernel.debug')) {
                        $originalClassName = substr($className, 0, -5);
                        $registry = $container->get('doctrine');

                        // Tries to auto-generate the proxy file
                        foreach ($registry->getEntityManagers() as $em) {

                            if ($em->getConfiguration()->getAutoGenerateProxyClasses()) {
                                $classes = $em->getMetadataFactory()->getAllMetadata();

                                foreach ($classes as $class) {
                                    $name = str_replace('\\', '', $class->name);

                                    if ($name == $originalClassName) {
                                        $em->getProxyFactory()->generateProxyClasses(array($class));
                                    }
                                }
                            }
                        }

                        clearstatcache($file);

                        if (!file_exists($file)) {
                            throw new \RuntimeException(sprintf('The proxy file "%s" does not exist. If you still have objects serialized in the session, you need to clear the session manually.', $file));
                        }
                    }

                    require $file;
                }
            });
        }
    }
}
