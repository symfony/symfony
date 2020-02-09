<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing\Loader;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Component\Routing\Loader\YamlFileLoader as BaseYamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class YamlFileLoader extends BaseYamlFileLoader
{
    private static $availableKeys = [
        'template' => ['context', 'max_age', 'shared_max_age', 'private'],
        'redirect_to_route' => ['permanent', 'ignore_attributes', 'keep_request_method', 'keep_query_params'],
        'redirect_to_url' => ['permanent', 'scheme', 'http_port', 'https_port', 'keep_request_method'],
        'gone' => ['permanent'],
    ];

    protected function validate($config, $name, $path)
    {
        if (\count($types = array_intersect_key($config, self::$availableKeys)) > 1) {
            throw new \InvalidArgumentException(sprintf('The routing file "%s" must not specify only one route type among "%s" keys for "%s".', str_replace('/', \DIRECTORY_SEPARATOR, $path), implode('", "', array_keys($types)), $name));
        }

        foreach (self::$availableKeys as $routeType => $availableKeys) {
            if (!isset($config[$routeType])) {
                continue;
            }

            if (isset($config['controller'])) {
                throw new \InvalidArgumentException(sprintf('The routing file "%s" must not specify both the "controller" and the "%s" keys for "%s".', str_replace('/', \DIRECTORY_SEPARATOR, $path), $routeType, $name));
            }

            // keys would be invalid for parent::validate(), but we use them below
            unset($config[$routeType]);
            foreach ($availableKeys as $key) {
                unset($config[$key]);
            }
        }

        parent::validate($config, $name, $path);
    }

    protected function parseRoute(RouteCollection $collection, $name, array $config, $path)
    {
        if (isset($config['template'])) {
            $config['defaults'] = array_merge($config['defaults'] ?? [], [
                '_controller' => TemplateController::class,
                'template' => $config['template'],
                'context' => $config['context'] ?? [],
                'maxAge' => $config['max_age'] ?? null,
                'sharedAge' => $config['shared_max_age'] ?? null,
                'private' => $config['private'] ?? null,
            ]);
        } elseif (isset($config['redirect_to_route'])) {
            $config['defaults'] = array_merge($config['defaults'] ?? [], [
                '_controller' => RedirectController::class.'::redirectAction',
                'route' => $config['redirect_to_route'],
                'permanent' => $config['permanent'] ?? false,
                'ignoreAttributes' => $config['ignore_attributes'] ?? false,
                'keepRequestMethod' => $config['keep_request_method'] ?? false,
                'keepQueryParams' => $config['keep_query_params'] ?? false,
            ]);
        } elseif (isset($config['redirect_to_url'])) {
            $config['defaults'] = array_merge($config['defaults'] ?? [], [
                '_controller' => RedirectController::class.'::urlRedirectAction',
                'path' => $config['redirect_to_url'],
                'permanent' => $config['permanent'] ?? false,
                'scheme' => $config['scheme'] ?? null,
                'keepRequestMethod' => $config['keep_request_method'] ?? false,
            ]);

            if (\array_key_exists('http_port', $config)) {
                $config['defaults']['httpPort'] = (int) $config['http_port'] ?: null;
            } elseif (\array_key_exists('http_port', $config)) {
                $config['defaults']['httpsPort'] = (int) $config['https_port'] ?: null;
            }
        } elseif (isset($config['gone'])) {
            $config['defaults'] = array_merge($config['defaults'] ?? [], [
                '_controller' => RedirectController::class.'::redirectAction',
                'route' => '',
            ]);

            if (isset($config['permanent'])) {
                $config['defaults']['permanent'] = $config['permanent'];
            }
        }

        parent::parseRoute($collection, $name, $config, $path);
    }
}
