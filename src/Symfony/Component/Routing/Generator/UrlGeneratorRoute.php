<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator;

/**
 * A route usable by the UrlGenerator
 *
 * @author Grégoire Passault <g.passault@gmail.com>
 */
class UrlGeneratorRoute
{
    private $variables;
    private $defaults;
    private $requirements;
    private $tokens;
    private $hostTokens;

    /**
     * Constructor.
     *
     */
    public function __construct(array $variables, array $defaults, array $requirements, array $tokens, array $hostTokens)
    {
        $this->variables = $variables;
        $this->defaults = $defaults;
        $this->requirements = $requirements;
        $this->tokens = $tokens;
        $this->hostTokens = $hostTokens;
    }

    public function getDefaults()
    {
        return $this->defaults;
    }

    public function getRequirements()
    {
        return $this->requirements;
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
}
