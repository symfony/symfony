<?php

namespace Symfony\Framework\PropelBundle;

use Symfony\Foundation\Bundle\Bundle as BaseBundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Framework\PropelBundle\DependencyInjection\PropelExtension;

class Bundle extends BaseBundle
{
    public function buildContainer(ContainerInterface $container)
    {
        Loader::registerExtension(new PropelExtension());
    }

    public function boot(ContainerInterface $container)
    {
        require_once $container->getParameter('propel.path').'/runtime/lib/Propel.php';

        if (0 === strncasecmp(PHP_SAPI, 'cli', 3)) {
            set_include_path($container->getParameter('propel.phing_path').'/classes'.PATH_SEPARATOR.get_include_path());
        }

        $kernel = $container->getKernelService();
        if (!file_exists($autoload = $kernel->getCacheDir().'/propel_autoload.php')) {
            $map = array();
            foreach ($kernel->getBundles() as $bundle) {
                if (!file_exists($file = $bundle->getPath().'/Resources/config/classmap.php')) {
                    continue;
                }

                $local = include($file);
                foreach ($local as $class => $path) {
                    $map[$class] = $bundle->getPath().'/'.$path;
                }
            }

            file_put_contents($autoload, '<?php return '.var_export($map, true).';');
        }

        $autoloader = \PropelAutoloader::getInstance();
        $autoloader->addClassPaths(include($autoload));
        $autoloader->register();

        $container->getPropelService();
    }
}
