<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Profiler\ProfileData\ConfigData;

/**
 * KernelData.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class KernelData extends ConfigData
{
    private $name;
    private $version;
    private $state;
    private $env;
    private $debug;
    private $bundles = array();

    public function __construct(KernelInterface $kernel, array $data)
    {
        parent::__construct($data);
        $this->version = Kernel::VERSION;
        $this->state = $this->determineSymfonyState();
        $this->name = $kernel->getName();
        $this->env = $kernel->getEnvironment();
        $this->debug = $kernel->isDebug();

        foreach ($kernel->getBundles() as $name => $bundle) {
            $this->bundles[$name] = $bundle->getPath();
        }
    }

    /**
     * Gets the application name.
     *
     * @return string The application name
     */
    public function getAppName()
    {
        return $this->name;
    }

    /**
     * Gets the Symfony version.
     *
     * @return string The Symfony version
     */
    public function getSymfonyVersion()
    {
        return $this->version;
    }

    /**
     * Returns the state of the current Symfony release.
     *
     * @return string One of: unknown, dev, stable, eom, eol
     */
    public function getSymfonyState()
    {
        return $this->state;
    }

    /**
     * Gets the environment.
     *
     * @return string The environment
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * Returns true if the debug is enabled.
     *
     * @return bool true if debug is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->debug;
    }

    public function getBundles()
    {
        return $this->bundles;
    }

    public function serialize()
    {
        return serialize(array(
            'data' => $this->data,
            'name' => $this->name,
            'version' => $this->version,
            'env' => $this->env,
            'debug' => $this->debug,
            'state' => $this->state,
            'bundles' => $this->bundles
        ));
    }

    public function unserialize($data)
    {
        $values = unserialize($data);

        $this->data = $values['data'];
        $this->name = $values['name'];
        $this->version = $values['version'];
        $this->env = $values['env'];
        $this->debug = $values['debug'];
        $this->state = $values['state'];
        $this->bundles = $values['bundles'];
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
            $versionState = 'eol'; //@codeCoverageIgnore
        } elseif ($now > $eom) {
            $versionState = 'eom'; //@codeCoverageIgnore
        } elseif ('' !== Kernel::EXTRA_VERSION) {
            $versionState = strtolower(Kernel::EXTRA_VERSION);  //@codeCoverageIgnore
        } else {
            $versionState = 'stable'; //@codeCoverageIgnore
        }

        return $versionState;
    }
}