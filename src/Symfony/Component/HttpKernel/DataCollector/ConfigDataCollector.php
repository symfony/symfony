<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ConfigDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConfigDataCollector extends DataCollector
{
    private $kernel;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
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
            'xdebug_enabled'  => extension_loaded('xdebug'),
            'eaccel_enabled'  => extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'),
            'apc_enabled'     => extension_loaded('apc') && ini_get('apc.enabled'),
            'xcache_enabled'  => extension_loaded('xcache') && ini_get('xcache.cacher'),
            'bundles'         => array(),
        );

        foreach ($this->kernel->getBundles() as $name => $bundle) {
            $this->data['bundles'][$name] = $bundle->getPath();
        }
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
        return $this->data['xdebug_enabled'];
    }

    /**
     * Returns true if EAccelerator is enabled.
     *
     * @return Boolean true if EAccelerator is enabled, false otherwise
     */
    public function hasEAccelerator()
    {
        return $this->data['eaccel_enabled'];
    }

    /**
     * Returns true if APC is enabled.
     *
     * @return Boolean true if APC is enabled, false otherwise
     */
    public function hasApc()
    {
        return $this->data['apc_enabled'];
    }

    /**
     * Returns true if XCache is enabled.
     *
     * @return Boolean true if XCache is enabled, false otherwise
     */
    public function hasXCache()
    {
        return $this->data['xcache_enabled'];
    }

    /**
     * Returns true if any accelerator is enabled.
     *
     * @return Boolean true if any accelerator is enabled, false otherwise
     */
    public function hasAccelerator()
    {
        return $this->hasApc() || $this->hasEAccelerator() || $this->hasXCache();
    }

    public function getBundles()
    {
        return $this->data['bundles'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'config';
    }
}
