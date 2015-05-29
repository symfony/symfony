<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\DataCollector;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Profiler\ProfileData\ConfigData;

/**
 * ConfigDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConfigDataCollector extends AbstractDataCollector implements RuntimeDataCollectorInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;
    private $name;
    private $version;

    /**
     * Constructor.
     *
     * @param string $name The name of the application using the web profiler
     * @param string $version The version of the application using the web profiler
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
     */
    public function setKernel(KernelInterface $kernel = null)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function collect()
    {
        $data = array(
            'app_name' => $this->name,
            'app_version' => $this->version,
            'token' => $this->token,
            'symfony_version' => Kernel::VERSION,
            'symfony_state' => 'unknown',
            'name' => isset($this->kernel) ? $this->kernel->getName() : 'n/a',
            'env' => isset($this->kernel) ? $this->kernel->getEnvironment() : 'n/a',
            'debug' => isset($this->kernel) ? $this->kernel->isDebug() : 'n/a',
            'php_version' => PHP_VERSION,
            'xdebug_enabled' => extension_loaded('xdebug'),
            'eaccel_enabled' => extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'),
            'apc_enabled' => extension_loaded('apc') && ini_get('apc.enabled'),
            'xcache_enabled' => extension_loaded('xcache') && ini_get('xcache.cacher'),
            'wincache_enabled' => extension_loaded('wincache') && ini_get('wincache.ocenabled'),
            'zend_opcache_enabled' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
            'bundles' => array(),
            'sapi_name' => php_sapi_name(),
        );

        if (isset($kernel)) {
            foreach ($kernel->getBundles() as $name => $bundle) {
                $data['bundles'][$name] = $bundle->getPath();
            }

            $data['symfony_state'] = $this->determineSymfonyState();
        }

        return new ConfigData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'config';
    }

    /**
     * Tries to retrieve information about the current Symfony version.
     *
     * @return string One of: dev, stable, eom, eol
     */
    private function determineSymfonyState()
    {
        $now = new \DateTime();
        $eom = \DateTime::createFromFormat('m/Y', Kernel::END_OF_MAINTENANCE)->modify('last day of this month');
        $eol = \DateTime::createFromFormat('m/Y', Kernel::END_OF_LIFE)->modify('last day of this month');

        if ($now > $eol) {
            $versionState = 'eol';
        } elseif ($now > $eom) {
            $versionState = 'eom';
        } elseif ('' !== Kernel::EXTRA_VERSION) {
            $versionState = 'dev';
        } else {
            $versionState = 'stable';
        }

        return $versionState;
    }
}
