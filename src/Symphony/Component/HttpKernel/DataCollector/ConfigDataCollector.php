<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\DataCollector;

use Symphony\Component\HttpKernel\KernelInterface;
use Symphony\Component\HttpKernel\Kernel;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\VarDumper\Caster\LinkStub;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ConfigDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;
    private $name;
    private $version;
    private $hasVarDumper;

    /**
     * @param string $name    The name of the application using the web profiler
     * @param string $version The version of the application using the web profiler
     */
    public function __construct(string $name = null, string $version = null)
    {
        $this->name = $name;
        $this->version = $version;
        $this->hasVarDumper = class_exists(LinkStub::class);
    }

    /**
     * Sets the Kernel associated with this Request.
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
            'app_name' => $this->name,
            'app_version' => $this->version,
            'token' => $response->headers->get('X-Debug-Token'),
            'symphony_version' => Kernel::VERSION,
            'symphony_state' => 'unknown',
            'name' => isset($this->kernel) ? $this->kernel->getName() : 'n/a',
            'env' => isset($this->kernel) ? $this->kernel->getEnvironment() : 'n/a',
            'debug' => isset($this->kernel) ? $this->kernel->isDebug() : 'n/a',
            'php_version' => PHP_VERSION,
            'php_architecture' => PHP_INT_SIZE * 8,
            'php_intl_locale' => class_exists('Locale', false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a',
            'php_timezone' => date_default_timezone_get(),
            'xdebug_enabled' => extension_loaded('xdebug'),
            'apcu_enabled' => extension_loaded('apcu') && ini_get('apc.enabled'),
            'zend_opcache_enabled' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
            'bundles' => array(),
            'sapi_name' => PHP_SAPI,
        );

        if (isset($this->kernel)) {
            foreach ($this->kernel->getBundles() as $name => $bundle) {
                $this->data['bundles'][$name] = $this->hasVarDumper ? new LinkStub($bundle->getPath()) : $bundle->getPath();
            }

            $this->data['symphony_state'] = $this->determineSymphonyState();
            $this->data['symphony_minor_version'] = sprintf('%s.%s', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION);
            $eom = \DateTime::createFromFormat('m/Y', Kernel::END_OF_MAINTENANCE);
            $eol = \DateTime::createFromFormat('m/Y', Kernel::END_OF_LIFE);
            $this->data['symphony_eom'] = $eom->format('F Y');
            $this->data['symphony_eol'] = $eol->format('F Y');
        }

        if (preg_match('~^(\d+(?:\.\d+)*)(.+)?$~', $this->data['php_version'], $matches) && isset($matches[2])) {
            $this->data['php_version'] = $matches[1];
            $this->data['php_version_extra'] = $matches[2];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data = array();
    }

    public function lateCollect()
    {
        $this->data = $this->cloneVar($this->data);
    }

    public function getApplicationName()
    {
        return $this->data['app_name'];
    }

    public function getApplicationVersion()
    {
        return $this->data['app_version'];
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
     * Gets the Symphony version.
     *
     * @return string The Symphony version
     */
    public function getSymphonyVersion()
    {
        return $this->data['symphony_version'];
    }

    /**
     * Returns the state of the current Symphony release.
     *
     * @return string One of: unknown, dev, stable, eom, eol
     */
    public function getSymphonyState()
    {
        return $this->data['symphony_state'];
    }

    /**
     * Returns the minor Symphony version used (without patch numbers of extra
     * suffix like "RC", "beta", etc.).
     *
     * @return string
     */
    public function getSymphonyMinorVersion()
    {
        return $this->data['symphony_minor_version'];
    }

    /**
     * Returns the human redable date when this Symphony version ends its
     * maintenance period.
     *
     * @return string
     */
    public function getSymphonyEom()
    {
        return $this->data['symphony_eom'];
    }

    /**
     * Returns the human redable date when this Symphony version reaches its
     * "end of life" and won't receive bugs or security fixes.
     *
     * @return string
     */
    public function getSymphonyEol()
    {
        return $this->data['symphony_eol'];
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
     * Gets the PHP version extra part.
     *
     * @return string|null The extra part
     */
    public function getPhpVersionExtra()
    {
        return isset($this->data['php_version_extra']) ? $this->data['php_version_extra'] : null;
    }

    /**
     * @return int The PHP architecture as number of bits (e.g. 32 or 64)
     */
    public function getPhpArchitecture()
    {
        return $this->data['php_architecture'];
    }

    /**
     * @return string
     */
    public function getPhpIntlLocale()
    {
        return $this->data['php_intl_locale'];
    }

    /**
     * @return string
     */
    public function getPhpTimezone()
    {
        return $this->data['php_timezone'];
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
     * @return bool true if debug is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->data['debug'];
    }

    /**
     * Returns true if the XDebug is enabled.
     *
     * @return bool true if XDebug is enabled, false otherwise
     */
    public function hasXDebug()
    {
        return $this->data['xdebug_enabled'];
    }

    /**
     * Returns true if APCu is enabled.
     *
     * @return bool true if APCu is enabled, false otherwise
     */
    public function hasApcu()
    {
        return $this->data['apcu_enabled'];
    }

    /**
     * Returns true if Zend OPcache is enabled.
     *
     * @return bool true if Zend OPcache is enabled, false otherwise
     */
    public function hasZendOpcache()
    {
        return $this->data['zend_opcache_enabled'];
    }

    public function getBundles()
    {
        return $this->data['bundles'];
    }

    /**
     * Gets the PHP SAPI name.
     *
     * @return string The environment
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
     * Tries to retrieve information about the current Symphony version.
     *
     * @return string One of: dev, stable, eom, eol
     */
    private function determineSymphonyState()
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
