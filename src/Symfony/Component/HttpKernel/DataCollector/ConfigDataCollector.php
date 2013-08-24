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
 *
 * @since v2.0.0
 */
class ConfigDataCollector extends DataCollector
{
    private $kernel;
    private $name;
    private $version;

    /**
     * Constructor.
     *
     * @param string $name    The name of the application using the web profiler
     * @param string $version The version of the application using the web profiler
     *
     * @since v2.2.1
     */
    public function __construct($name = null, $version = null)
    {
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * Sets the Kernel associated with this Request.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     *
     * @since v2.2.0
     */
    public function setKernel(KernelInterface $kernel = null)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'app_name'             => $this->name,
            'app_version'          => $this->version,
            'token'                => $response->headers->get('X-Debug-Token'),
            'symfony_version'      => Kernel::VERSION,
            'name'                 => isset($this->kernel) ? $this->kernel->getName() : 'n/a',
            'env'                  => isset($this->kernel) ? $this->kernel->getEnvironment() : 'n/a',
            'debug'                => isset($this->kernel) ? $this->kernel->isDebug() : 'n/a',
            'php_version'          => PHP_VERSION,
            'xdebug_enabled'       => extension_loaded('xdebug'),
            'eaccel_enabled'       => extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'),
            'apc_enabled'          => extension_loaded('apc') && ini_get('apc.enabled'),
            'xcache_enabled'       => extension_loaded('xcache') && ini_get('xcache.cacher'),
            'wincache_enabled'     => extension_loaded('wincache') && ini_get('wincache.ocenabled'),
            'zend_opcache_enabled' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
            'bundles'              => array(),
            'sapi_name'            => php_sapi_name()
        );

        if (isset($this->kernel)) {
            foreach ($this->kernel->getBundles() as $name => $bundle) {
                $this->data['bundles'][$name] = $bundle->getPath();
            }
        }
    }

    /**
     * @since v2.2.1
     */
    public function getApplicationName()
    {
        return $this->data['app_name'];
    }

    /**
     * @since v2.2.1
     */
    public function getApplicationVersion()
    {
        return $this->data['app_version'];
    }

    /**
     * Gets the token.
     *
     * @return string The token
     *
     * @since v2.0.0
     */
    public function getToken()
    {
        return $this->data['token'];
    }

    /**
     * Gets the Symfony version.
     *
     * @return string The Symfony version
     *
     * @since v2.0.0
     */
    public function getSymfonyVersion()
    {
        return $this->data['symfony_version'];
    }

    /**
     * Gets the PHP version.
     *
     * @return string The PHP version
     *
     * @since v2.0.0
     */
    public function getPhpVersion()
    {
        return $this->data['php_version'];
    }

    /**
     * Gets the application name.
     *
     * @return string The application name
     *
     * @since v2.0.0
     */
    public function getAppName()
    {
        return $this->data['name'];
    }

    /**
     * Gets the environment.
     *
     * @return string The environment
     *
     * @since v2.0.0
     */
    public function getEnv()
    {
        return $this->data['env'];
    }

    /**
     * Returns true if the debug is enabled.
     *
     * @return Boolean true if debug is enabled, false otherwise
     *
     * @since v2.0.0
     */
    public function isDebug()
    {
        return $this->data['debug'];
    }

    /**
     * Returns true if the XDebug is enabled.
     *
     * @return Boolean true if XDebug is enabled, false otherwise
     *
     * @since v2.0.0
     */
    public function hasXDebug()
    {
        return $this->data['xdebug_enabled'];
    }

    /**
     * Returns true if EAccelerator is enabled.
     *
     * @return Boolean true if EAccelerator is enabled, false otherwise
     *
     * @since v2.0.0
     */
    public function hasEAccelerator()
    {
        return $this->data['eaccel_enabled'];
    }

    /**
     * Returns true if APC is enabled.
     *
     * @return Boolean true if APC is enabled, false otherwise
     *
     * @since v2.0.0
     */
    public function hasApc()
    {
        return $this->data['apc_enabled'];
    }

    /**
     * Returns true if Zend OPcache is enabled
     *
     * @return Boolean true if Zend OPcache is enabled, false otherwise
     *
     * @since v2.3.0
     */
    public function hasZendOpcache()
    {
        return $this->data['zend_opcache_enabled'];
    }

    /**
     * Returns true if XCache is enabled.
     *
     * @return Boolean true if XCache is enabled, false otherwise
     *
     * @since v2.0.0
     */
    public function hasXCache()
    {
        return $this->data['xcache_enabled'];
    }

    /**
     * Returns true if WinCache is enabled.
     *
     * @return Boolean true if WinCache is enabled, false otherwise
     *
     * @since v2.2.0
     */
    public function hasWinCache()
    {
        return $this->data['wincache_enabled'];
    }

    /**
     * Returns true if any accelerator is enabled.
     *
     * @return Boolean true if any accelerator is enabled, false otherwise
     *
     * @since v2.0.0
     */
    public function hasAccelerator()
    {
        return $this->hasApc() || $this->hasZendOpcache() || $this->hasEAccelerator() || $this->hasXCache() || $this->hasWinCache();
    }

    /**
     * @since v2.0.0
     */
    public function getBundles()
    {
        return $this->data['bundles'];
    }

    /**
     * Gets the PHP SAPI name.
     *
     * @return string The environment
     *
     * @since v2.2.0
     */
    public function getSapiName()
    {
        return $this->data['sapi_name'];
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'config';
    }
}
