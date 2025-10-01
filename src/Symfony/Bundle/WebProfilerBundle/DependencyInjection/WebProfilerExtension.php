<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\DependencyInjection;

use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * WebProfilerExtension.
 *
 * Usage:
 *
 *     <webprofiler:config
 *        toolbar="true"
 *        intercept-redirects="true"
 *     />
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WebProfilerExtension extends Extension
{
    /**
     * Loads the web profiler configuration.
     *
     * @param array $configs An array of configuration settings
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('profiler.php');

        if ($config['toolbar']['enabled'] || $config['intercept_redirects']) {
            $loader->load('toolbar.php');
            $container->getDefinition('web_profiler.debug_toolbar')->replaceArgument(4, $config['excluded_ajax_paths']);
            $container->getDefinition('web_profiler.debug_toolbar')->replaceArgument(7, $config['toolbar']['ajax_replace']);
            $container->setParameter('web_profiler.debug_toolbar.intercept_redirects', $config['intercept_redirects']);
            $container->setParameter('web_profiler.debug_toolbar.mode', $config['toolbar']['enabled'] ? WebDebugToolbarListener::ENABLED : WebDebugToolbarListener::DISABLED);
        }

        $container->getDefinition('debug.file_link_formatter')
            ->replaceArgument(3, new ServiceClosureArgument(new Reference('debug.file_link_formatter.url_format')));
    }

    public function getXsdValidationBasePath(): string|false
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/webprofiler';
    }
}
