<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * ApacheUrlMatcher matches URL based on Apache mod_rewrite matching (see ApacheMatcherDumper).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ApacheUrlMatcher extends UrlMatcher
{
    protected $defaults;
    protected $context;

    /**
     * Constructor.
     *
     * @param array $context  The context
     * @param array $defaults The default values
     */
    public function __construct(array $context = array(), array $defaults = array())
    {
        $this->context = $context;
        $this->defaults = $defaults;
    }

    /**
     * Tries to match a URL based on Apache mod_rewrite matching.
     *
     * Returns false if no route matches the URL.
     *
     * @param  string $url URL to be parsed
     *
     * @return array|false An array of parameters or false if no route matches
     */
    public function match($url)
    {
        if (!isset($_SERVER['_ROUTING__route'])) {
            // fall-back to the default UrlMatcher
            return parent::match($url);
        }

        $parameters = array();
        foreach ($_SERVER as $key => $value) {
            if ('_ROUTING_' === substr($key, 0, 9)) {
                $parameters[substr($key, 9)] = $value;
                unset($_SERVER[$key]);
            }
        }

        return $parameters;
    }
}
