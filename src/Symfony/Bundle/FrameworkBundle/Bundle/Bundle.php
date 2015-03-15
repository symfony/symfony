<?php

namespace Symfony\Bundle\FrameworkBundle\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle as BaseBundle;

class Bundle extends BaseBundle
{
    private $twigPaths = array();

    protected function addTwigPath($templateDirectory, $namespace)
    {
        $this->twigPaths[$namespace] = $templateDirectory;
    }

    public function build(ContainerBuilder $container)
    {
        foreach ($this->twigPaths as $namespace => $directory) {
            if (!is_dir($directory)) {
                throw new \InvalidArgumentException(sprintf(
                    'Directory "%s" does not exist, so it cannot be added as a Twig path', $directory
                ));
            }

            $container->prependExtensionConfig('twig', array(
                'paths' => array($directory => $namespace)
            ));
        }
    }


}