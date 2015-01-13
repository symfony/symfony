<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class KernelExtension extends \Twig_Extension
{
    private $environment;
    private $debug;

    public function __construct($environment, $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('is_debug', array($this, 'isDebug')),
            new \Twig_SimpleFunction('env', array($this, 'getEnvironment')),
        );
    }

    /**
     * Returns the current app environment.
     *
     * @return string The current environment string (e.g 'dev')
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Returns the current debug flag.
     *
     * @return bool Whether debug is enabled or not
     */
    public function isDebug()
    {
        return $this->debug;
    }

    public function getName()
    {
        return 'kernel';
    }
}
