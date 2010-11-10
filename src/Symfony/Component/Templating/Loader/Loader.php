<?php

namespace Symfony\Component\Templating\Loader;

use Symfony\Component\Templating\DebuggerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Loader is the base class for all template loader classes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Loader implements LoaderInterface
{
    protected $debugger;
    protected $defaultOptions;

    public function __construct()
    {
        $this->defaultOptions = array('renderer' => 'php');
    }

    /**
     * Sets the debugger to use for this loader.
     *
     * @param DebuggerInterface $debugger A debugger instance
     */
    public function setDebugger(DebuggerInterface $debugger)
    {
        $this->debugger = $debugger;
    }

    /**
     * Sets a default option.
     *
     * @param string $name  The option name
     * @param mixed  $value The option value
     */
    public function setDefaultOption($name, $value)
    {
        $this->defaultOptions[$name] = $value;
    }

    /**
     * Merges the default options with the given set of options.
     *
     * @param array $options An array of options
     *
     * @return array The merged set of options
     */
    protected function mergeDefaultOptions(array $options)
    {
        return array_merge($this->defaultOptions, $options);
    }
}
