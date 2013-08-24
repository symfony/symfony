<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

/**
 * CompiledRoutes are returned by the RouteCompiler class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class CompiledRoute
{
    private $variables;
    private $tokens;
    private $staticPrefix;
    private $regex;
    private $pathVariables;
    private $hostVariables;
    private $hostRegex;
    private $hostTokens;

    /**
     * Constructor.
     *
     * @param string      $staticPrefix       The static prefix of the compiled route
     * @param string      $regex              The regular expression to use to match this route
     * @param array       $tokens             An array of tokens to use to generate URL for this route
     * @param array       $pathVariables      An array of path variables
     * @param string|null $hostRegex          Host regex
     * @param array       $hostTokens         Host tokens
     * @param array       $hostVariables      An array of host variables
     * @param array       $variables          An array of variables (variables defined in the path and in the host patterns)
     *
     * @since v2.2.0
     */
    public function __construct($staticPrefix, $regex, array $tokens, array $pathVariables, $hostRegex = null, array $hostTokens = array(), array $hostVariables = array(), array $variables = array())
    {
        $this->staticPrefix = (string) $staticPrefix;
        $this->regex = $regex;
        $this->tokens = $tokens;
        $this->pathVariables = $pathVariables;
        $this->hostRegex = $hostRegex;
        $this->hostTokens = $hostTokens;
        $this->hostVariables = $hostVariables;
        $this->variables = $variables;
    }

    /**
     * Returns the static prefix.
     *
     * @return string The static prefix
     *
     * @since v2.0.0
     */
    public function getStaticPrefix()
    {
        return $this->staticPrefix;
    }

    /**
     * Returns the regex.
     *
     * @return string The regex
     *
     * @since v2.0.0
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * Returns the host regex
     *
     * @return string|null The host regex or null
     *
     * @since v2.2.0
     */
    public function getHostRegex()
    {
        return $this->hostRegex;
    }

    /**
     * Returns the tokens.
     *
     * @return array The tokens
     *
     * @since v2.0.0
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * Returns the host tokens.
     *
     * @return array The tokens
     *
     * @since v2.2.0
     */
    public function getHostTokens()
    {
        return $this->hostTokens;
    }

    /**
     * Returns the variables.
     *
     * @return array The variables
     *
     * @since v2.0.0
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * Returns the path variables.
     *
     * @return array The variables
     *
     * @since v2.2.0
     */
    public function getPathVariables()
    {
        return $this->pathVariables;
    }

    /**
     * Returns the host variables.
     *
     * @return array The variables
     *
     * @since v2.2.0
     */
    public function getHostVariables()
    {
        return $this->hostVariables;
    }

}
