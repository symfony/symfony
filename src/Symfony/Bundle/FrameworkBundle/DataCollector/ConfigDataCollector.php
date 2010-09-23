<?php

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ConfigDataCollector.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ConfigDataCollector extends DataCollector
{
    protected $kernel;
    protected $router;

    /**
     * Constructor.
     *
     * @param Kernel          $kernel A Kernel instance
     * @param RouterInterface $router A Router instance
     */
    public function __construct(Kernel $kernel, RouterInterface $router = null)
    {
        $this->kernel = $kernel;
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'token'           => $response->headers->get('X-Debug-Token'),
            'symfony_version' => Kernel::VERSION,
            'name'            => $this->kernel->getName(),
            'env'             => $this->kernel->getEnvironment(),
            'debug'           => $this->kernel->isDebug(),
            'php_version'     => PHP_VERSION,
            'xdebug'          => extension_loaded('xdebug'),
            'accel'           => (
                (extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'))
                ||
                (extension_loaded('apc') && ini_get('apc.enabled'))
                ||
                (extension_loaded('xcache') && ini_get('xcache.cacher'))
            ),
        );
    }

    /**
     * Gets the URL.
     *
     * @return string The URL
     */
    public function getUrl()
    {
        if (null !== $this->router) {
            try {
                return $this->router->generate('_profiler', array('token' => $this->data['token']));
            } catch (\Exception $e) {
                // the route is not registered
            }
        }

        return false;
    }

    /**
     * Gets the token.
     *
     * @return string The token
     */
    public function getToken()
    {
        return $this->data['token'];
    }

    /**
     * Gets the Symfony version.
     *
     * @return string The Symfony version
     */
    public function getSymfonyVersion()
    {
        return $this->data['symfony_version'];
    }

    /**
     * Gets the PHP version.
     *
     * @return string The PHP version
     */
    public function getPhpVersion()
    {
        return $this->data['php_version'];
    }

    /**
     * Gets the application name.
     *
     * @return string The application name
     */
    public function getAppName()
    {
        return $this->data['name'];
    }

    /**
     * Gets the environment.
     *
     * @return string The environment
     */
    public function getEnv()
    {
        return $this->data['env'];
    }

    /**
     * Returns true if the debug is enabled.
     *
     * @return Boolean true if debug is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->data['debug'];
    }

    /**
     * Returns true if the XDebug is enabled.
     *
     * @return Boolean true if XDebug is enabled, false otherwise
     */
    public function hasXDebug()
    {
        return $this->data['xdebug'];
    }

    /**
     * Returns true if an accelerator is enabled.
     *
     * @return Boolean true if an accelerator is enabled, false otherwise
     */
    public function hasAccelerator()
    {
        return $this->data['accel'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'config';
    }
}
