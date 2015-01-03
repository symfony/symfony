<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * DeprecationParameterBag is ParameterBag able to notify users when using a deprecated parameter.
 *
 * @author Roger Llopart Pla <lumbendil@gmail.com>
 */
class DeprecationParameterBag extends ParameterBag
{
    /**
     * Collection of deprecated paths and messages.
     *
     * @var array
     */
    protected $deprecations;

    /**
     * Constructor
     *
     * @param ParameterBag $bag Parent bag.
     * @param array $deprecations A collection of deprecation paths and messages.
     */
    public function __construct(ParameterBag $bag, array $deprecations = array())
    {
        $this->parameters = $bag->all();
        $this->deprecations = $deprecations;
    }

    /**
     * Adds a deprecation message.
     *
     * @param string $path
     * @param string $message
     */
    public function addDeprecation($path, $message)
    {
        $this->deprecations[$path] = $message;
    }

    public function get($path, $default = null, $deep = false)
    {
        if (isset($this->deprecations[$path])) {
            trigger_error($this->deprecations[$path], E_USER_DEPRECATED);
        }

        return parent::get($path, $default, $deep);
    }
} 
