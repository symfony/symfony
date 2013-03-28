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
 * ConfigDataCollector holds information about the configuration.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConfigDataCollector extends DataCollector
{
    private $kernel;

    /**
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function setKernel(KernelInterface $kernel = null)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'token'            => $response->headers->get('X-Debug-Token'),
            'symfony_version'  => Kernel::VERSION,
            'name'             => isset($this->kernel) ? $this->kernel->getName() : 'n/a',
            'env'              => isset($this->kernel) ? $this->kernel->getEnvironment() : 'n/a',
            'debug'            => isset($this->kernel) ? $this->kernel->isDebug() : 'n/a',
            'php_version'      => PHP_VERSION,
            'xdebug_enabled'   => extension_loaded('xdebug'),
            'eaccel_enabled'   => extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'),
            'apc_enabled'      => extension_loaded('apc') && ini_get('apc.enabled'),
            'xcache_enabled'   => extension_loaded('xcache') && ini_get('xcache.cacher'),
            'wincache_enabled' => extension_loaded('wincache') && ini_get('wincache.ocenabled'),
            'opcache_enabled'  => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
            'bundles'          => array(),
            'sapi_name'        => php_sapi_name(),
        );

        if (isset($this->kernel)) {
            foreach ($this->kernel->getBundles() as $name => $bundle) {
                $this->data['bundles'][$name] = $bundle->getPath();
            }
        }
    }

    /**
     * @return string The token
     */
    public function getToken()
    {
        return $this->data['token'];
    }

    /**
     * @return string The Symfony version
     */
    public function getSymfonyVersion()
    {
        return $this->data['symfony_version'];
    }

    /**
     * @return string The PHP version
     */
    public function getPhpVersion()
    {
        return $this->data['php_version'];
    }

    /**
     * @return string The application name
     */
    public function getAppName()
    {
        return $this->data['name'];
    }

    /**
     * @return string The environment
     */
    public function getEnv()
    {
        return $this->data['env'];
    }

    /**
     * @return Boolean Whether debug is enabled
     */
    public function isDebug()
    {
        return $this->data['debug'];
    }

    /**
     * @return Boolean Whether XDebug is enabled
     */
    public function hasXDebug()
    {
        return $this->isExtensionEnabled('xdebug');
    }

    /**
     * @return Boolean Whether EAccelerator is enabled
     */
    public function hasEAccelerator()
    {
        return $this->isExtensionEnabled('eaccel');
    }

    /**
     * @return Boolean Whether APC is enabled, false otherwise
     */
    public function hasApc()
    {
        return $this->isExtensionEnabled('apc');
    }

    /**
     * @return Boolean Whether XCache is enabled
     */
    public function hasXCache()
    {
        return $this->isExtensionEnabled('xcache');
    }

    /**
     * @return Boolean Whether WinCache is enabled
     */
    public function hasWinCache()
    {
        return $this->isExtensionEnabled('wincache');
    }

    /**
     * @return Boolean Whether Zend OPcache is enabled
     */
    public function hasOpcache()
    {
        return $this->isExtensionEnabled('opcache');
    }

    /**
     * @return Boolean Whether any opcode cache is enabled.
     */
    public function hasAccelerator()
    {
        return $this->hasApc() || $this->hasEAccelerator() || $this->hasXCache() || $this->hasWinCache() || $this->hasOpcache();
    }

    /**
     * @return array An associative array of bundles (name => root path)
     */
    public function getBundles()
    {
        return $this->data['bundles'];
    }

    /**
     * @return string the PHP SAPI name
     */
    public function getSapiName()
    {
        return $this->data['sapi_name'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'config';
    }

    /**
     * @param string $name The name of the extension
     *
     * @return Boolean whether the given extension is enabled
     */
    protected function isExtensionEnabled($name)
    {
        $ext = $name.'_enabled';
        // return false when not set to keep BC
        return isset($this->data[$ext]) ? (Boolean) $this->data[$ext] : false;
    }
}
