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
 */
class CompiledRoute
{
    private $variables;
    private $tokens;
    private $staticPrefix;
    private $regex;
    private $pathVariables;
    private $hostnameVariables;
    private $hostnameRegex;
    private $hostnameTokens;

    /**
     * Constructor.
     *
     * @param string $staticPrefix      The static prefix of the compiled route
     * @param string $regex             The regular expression to use to match this route
     * @param array  $tokens            An array of tokens to use to generate URL for this route
     * @param array  $pathVariables     An array of path variables
     * @param array  $hostnameRegex     Hostname regex
     * @param array  $hostnameTokens    Hostname tokens
     * @param array  $hostnameVariables An array of hostname variables
     * @param array  $variables         An array of variables (variables defined in the path and in the hostname patterns)
     */
    public function __construct($staticPrefix, $regex, array $tokens, array $pathVariables, $hostnameRegex = null, array $hostnameTokens = array(), array $hostnameVariables = array(), array $variables = array())
    {
        $this->staticPrefix = $staticPrefix;
        $this->regex = $regex;
        $this->tokens = $tokens;
        $this->pathVariables = $pathVariables;
        $this->hostnameRegex = $hostnameRegex;
        $this->hostnameTokens = $hostnameTokens;
        $this->hostnameVariables = $hostnameVariables;
        $this->variables = $variables;
    }

    /**
     * Returns the static prefix.
     *
     * @return string The static prefix
     */
    public function getStaticPrefix()
    {
        return $this->staticPrefix;
    }

    /**
     * Returns the regex.
     *
     * @return string The regex
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * Returns the hostname regex
     *
     * @return string The hostname regex
     */
    public function getHostnameRegex()
    {
        return $this->hostnameRegex;
    }

    /**
     * Returns the tokens.
     *
     * @return array The tokens
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * Returns the hostname tokens.
     *
     * @return array The tokens
     */
    public function getHostnameTokens()
    {
        return $this->hostnameTokens;
    }

    /**
     * Returns the variables.
     *
     * @return array The variables
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * Returns the path variables.
     *
     * @return array The variables
     */
    public function getPathVariables()
    {
        return $this->pathVariables;
    }

    /**
     * Returns the hostname variables.
     *
     * @return array The variables
     */
    public function getHostnameVariables()
    {
        return $this->hostnameVariables;
    }

}
