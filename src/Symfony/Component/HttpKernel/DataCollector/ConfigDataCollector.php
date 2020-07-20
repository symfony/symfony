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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ConfigDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

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
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data = [
            'token' => $response->headers->get('X-Debug-Token'),
            'symfony_version' => Kernel::VERSION,
            'symfony_state' => 'unknown',
            'env' => isset($this->kernel) ? $this->kernel->getEnvironment() : 'n/a',
            'debug' => isset($this->kernel) ? $this->kernel->isDebug() : 'n/a',
            'php_version' => PHP_VERSION,
            'php_architecture' => PHP_INT_SIZE * 8,
            'php_intl_locale' => class_exists('Locale', false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a',
            'php_timezone' => date_default_timezone_get(),
            'xdebug_enabled' => \extension_loaded('xdebug'),
            'apcu_enabled' => \extension_loaded('apcu') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN),
            'zend_opcache_enabled' => \extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN),
            'bundles' => [],
            'sapi_name' => \PHP_SAPI,
        ];

        if (isset($this->kernel)) {
            foreach ($this->kernel->getBundles() as $name => $bundle) {
                $this->data['bundles'][$name] = new ClassStub(\get_class($bundle));
            }

            $this->data['symfony_state'] = $this->determineSymfonyState();
            $this->data['symfony_minor_version'] = sprintf('%s.%s', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION);
            $this->data['symfony_lts'] = 4 === Kernel::MINOR_VERSION;
            $eom = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_MAINTENANCE);
            $eol = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_LIFE);
            $this->data['symfony_eom'] = $eom->format('F Y');
            $this->data['symfony_eol'] = $eol->format('F Y');
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
        $this->data = [];
    }

    public function lateCollect()
    {
        $this->data = $this->cloneVar($this->data);
    }

    /**
     * Gets the token.
     *
     * @return string|null The token
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
     * Returns the state of the current Symfony release.
     *
     * @return string One of: unknown, dev, stable, eom, eol
     */
    public function getSymfonyState()
    {
        return $this->data['symfony_state'];
    }

    /**
     * Returns the minor Symfony version used (without patch numbers of extra
     * suffix like "RC", "beta", etc.).
     *
     * @return string
     */
    public function getSymfonyMinorVersion()
    {
        return $this->data['symfony_minor_version'];
    }

    /**
     * Returns if the current Symfony version is a Long-Term Support one.
     */
    public function isSymfonyLts(): bool
    {
        return $this->data['symfony_lts'];
    }

    /**
     * Returns the human redable date when this Symfony version ends its
     * maintenance period.
     *
     * @return string
     */
    public function getSymfonyEom()
    {
        return $this->data['symfony_eom'];
    }

    /**
     * Returns the human redable date when this Symfony version reaches its
     * "end of life" and won't receive bugs or security fixes.
     *
     * @return string
     */
    public function getSymfonyEol()
    {
        return $this->data['symfony_eol'];
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
     * Tries to retrieve information about the current Symfony version.
     *
     * @return string One of: dev, stable, eom, eol
     */
    private function determineSymfonyState(): string
    {
        $now = new \DateTime();
        $eom = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_MAINTENANCE)->modify('last day of this month');
        $eol = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_LIFE)->modify('last day of this month');

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
