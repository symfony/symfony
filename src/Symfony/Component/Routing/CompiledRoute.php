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
class CompiledRoute implements \Serializable
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
     * @var array
     */
    private $regexVariablesAliases;

    /**
     * @var array
     */
    private $hostRegexVariablesAliases;

    /**
     * Constructor.
     *
     * @param string      $staticPrefix              The static prefix of the compiled route
     * @param string      $regex                     The regular expression to use to match this route
     * @param array       $tokens                    An array of tokens to use to generate URL for this route
     * @param array       $pathVariables             An array of path variables
     * @param string|null $hostRegex                 Host regex
     * @param array       $hostTokens                Host tokens
     * @param array       $hostVariables             An array of host variables
     * @param array       $variables                 An array of variables (variables defined in the path and in the host patterns)
     * @param array       $regexVariablesAliases     An array containing path variables aliases as keys and actual path variables names as values
     * @param array       $hostRegexVariablesAliases An array containing host variables aliases as keys and actual host variables names as values
     */
    public function __construct($staticPrefix, $regex, array $tokens, array $pathVariables, $hostRegex = null, array $hostTokens = array(), array $hostVariables = array(), array $variables = array(), array $regexVariablesAliases = array(), array $hostRegexVariablesAliases = array())
    {
        $this->staticPrefix = (string) $staticPrefix;
        $this->regex = $regex;
        $this->tokens = $tokens;
        $this->pathVariables = $pathVariables;
        $this->hostRegex = $hostRegex;
        $this->hostTokens = $hostTokens;
        $this->hostVariables = $hostVariables;
        $this->variables = $variables;
        $this->regexVariablesAliases = $regexVariablesAliases;
        $this->hostRegexVariablesAliases = $hostRegexVariablesAliases;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array(
            'vars' => $this->variables,
            'path_prefix' => $this->staticPrefix,
            'path_regex' => $this->regex,
            'path_tokens' => $this->tokens,
            'path_vars' => $this->pathVariables,
            'path_regex_vars_aliases' => $this->regexVariablesAliases,
            'host_regex' => $this->hostRegex,
            'host_tokens' => $this->hostTokens,
            'host_vars' => $this->hostVariables,
            'host_regex_vars_aliases' => $this->hostRegexVariablesAliases,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->variables = $data['vars'];
        $this->staticPrefix = $data['path_prefix'];
        $this->regex = $data['path_regex'];
        $this->tokens = $data['path_tokens'];
        $this->pathVariables = $data['path_vars'];
        $this->regexVariablesAliases = $data['path_regex_vars_aliases'];
        $this->hostRegex = $data['host_regex'];
        $this->hostTokens = $data['host_tokens'];
        $this->hostVariables = $data['host_vars'];
        $this->hostRegexVariablesAliases = $data['host_regex_vars_aliases'];
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
     * Returns the host regex.
     *
     * @return string|null The host regex or null
     */
    public function getHostRegex()
    {
        return $this->hostRegex;
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
     * Returns the host tokens.
     *
     * @return array The tokens
     */
    public function getHostTokens()
    {
        return $this->hostTokens;
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
     * Returns the host variables.
     *
     * @return array The variables
     */
    public function getHostVariables()
    {
        return $this->hostVariables;
    }

    /**
     * @return array
     */
    public function getRegexVariablesAliases()
    {
        return $this->regexVariablesAliases;
    }

    /**
     * @return array
     */
    public function getHostRegexVariablesAliases()
    {
        return $this->hostRegexVariablesAliases;
    }
}
