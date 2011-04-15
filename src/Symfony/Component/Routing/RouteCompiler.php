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
 * RouteCompiler compiles Route instances to CompiledRoute instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouteCompiler implements RouteCompilerInterface
{
    protected $options;
    protected $route;
    protected $variables;
    protected $firstOptional;
    protected $segments;
    protected $tokens;
    protected $staticPrefix;
    protected $regex;

    /**
     * Compiles the current route instance.
     *
     * @param Route $route A Route instance
     *
     * @return CompiledRoute A CompiledRoute instance
     */
    public function compile(Route $route)
    {
        $this->route = $route;
        $this->firstOptional = 0;
        $this->segments = array();
        $this->variables = array();
        $this->tokens = array();
        $this->staticPrefix = '';
        $this->regex = '';
        $this->options = $this->getOptions();

        $this->preCompile();

        $this->tokenize();

        foreach ($this->tokens as $token) {
            call_user_func_array(array($this, 'compileFor'.ucfirst(array_shift($token))), $token);
        }

        $this->postCompile();

        $separator = '';
        if (count($this->tokens)) {
            $lastToken = $this->tokens[count($this->tokens) - 1];
            $separator = 'separator' == $lastToken[0] ? $lastToken[2] : '';
        }

        $this->regex = "#^".implode("", $this->segments)."".preg_quote($separator, '#')."$#x";

        // optimize tokens for generation
        $tokens = array();
        foreach ($this->tokens as $i => $token) {
            if ($i + 1 === count($this->tokens) && 'separator' === $token[0]) {
                // trailing /
                $tokens[] = array('text', $token[2], '', null);
            } elseif ('separator' !== $token[0]) {
                $tokens[] = $token;
            }
        }

        $tokens = array_reverse($tokens);

        return new CompiledRoute($this->route, $this->staticPrefix, $this->regex, $tokens, $this->variables);
    }

    /**
     * Pre-compiles a route.
     */
    protected function preCompile()
    {
    }

    /**
     * Post-compiles a route.
     */
    protected function postCompile()
    {
        // all segments after the last static segment are optional
        // be careful, the n-1 is optional only if n is empty
        for ($i = $this->firstOptional, $max = count($this->segments); $i < $max; $i++) {
            $this->segments[$i] = (0 == $i ? '/?' : '').str_repeat(' ', $i - $this->firstOptional).'(?:'.$this->segments[$i];
            $this->segments[] = str_repeat(' ', $max - $i - 1).')?';
        }

        $this->staticPrefix = '';
        foreach ($this->tokens as $token) {
            switch ($token[0]) {
                case 'separator':
                    break;
                case 'text':
                    // text is static
                    $this->staticPrefix .= $token[1].$token[2];
                    break;
                default:
                    // everything else indicates variable parts. break switch and for loop
                    break 2;
            }
        }
    }

    /**
     * Tokenizes the route.
     *
     * @throws \InvalidArgumentException When route can't be parsed
     */
    private function tokenize()
    {
        $this->tokens = array();
        $buffer = $this->route->getPattern();
        $afterASeparator = false;
        $currentSeparator = '';

        // a route is an array of (separator + variable) or (separator + text) segments
        while (strlen($buffer)) {
            if (false !== $this->tokenizeBufferBefore($buffer, $tokens, $afterASeparator, $currentSeparator)) {
                // a custom token
                $this->customToken = true;
            } else if ($afterASeparator && preg_match('#^\{([\w\d_]+)\}#', $buffer, $match)) {
                // a variable
                $this->tokens[] = array('variable', $currentSeparator, $match[0], $match[1]);

                $currentSeparator = '';
                $buffer = substr($buffer, strlen($match[0]));
                $afterASeparator = false;
            } else if ($afterASeparator && preg_match('#^('.$this->options['text_regex'].')(?:'.$this->options['segment_separators_regex'].'|$)#', $buffer, $match)) {
                // a text
                $this->tokens[] = array('text', $currentSeparator, $match[1], null);

                $currentSeparator = '';
                $buffer = substr($buffer, strlen($match[1]));
                $afterASeparator = false;
            } else if (!$afterASeparator && preg_match('#^'.$this->options['segment_separators_regex'].'#', $buffer, $match)) {
                // a separator
                $this->tokens[] = array('separator', $currentSeparator, $match[0], null);

                $currentSeparator = $match[0];
                $buffer = substr($buffer, strlen($match[0]));
                $afterASeparator = true;
            } else if (false !== $this->tokenizeBufferAfter($buffer, $tokens, $afterASeparator, $currentSeparator)) {
                // a custom token
                $this->customToken = true;
            } else {
                // parsing problem
                throw new \InvalidArgumentException(sprintf('Unable to parse "%s" route near "%s".', $this->route->getPattern(), $buffer));
            }
        }
    }

    /**
     * Tokenizes the buffer before default logic is applied.
     *
     * This method must return false if the buffer has not been parsed.
     *
     * @param string   $buffer           The current route buffer
     * @param array    $tokens           An array of current tokens
     * @param Boolean  $afterASeparator  Whether the buffer is just after a separator
     * @param string   $currentSeparator The last matched separator
     *
     * @return Boolean true if a token has been generated, false otherwise
     */
    protected function tokenizeBufferBefore(&$buffer, &$tokens, &$afterASeparator, &$currentSeparator)
    {
        return false;
    }

    /**
     * Tokenizes the buffer after default logic is applied.
     *
     * This method must return false if the buffer has not been parsed.
     *
     * @param string   $buffer           The current route buffer
     * @param array    $tokens           An array of current tokens
     * @param Boolean  $afterASeparator  Whether the buffer is just after a separator
     * @param string   $currentSeparator The last matched separator
     *
     * @return Boolean true if a token has been generated, false otherwise
     */
    protected function tokenizeBufferAfter(&$buffer, &$tokens, &$afterASeparator, &$currentSeparator)
    {
        return false;
    }

    protected function compileForText($separator, $text)
    {
        $this->firstOptional = count($this->segments) + 1;

        $this->segments[] = preg_quote($separator, '#').preg_quote($text, '#');
    }

    protected function compileForVariable($separator, $name, $variable)
    {
        if (null === $requirement = $this->route->getRequirement($variable)) {
            $requirement = $this->options['variable_content_regex'];
        }

        $this->segments[] = preg_quote($separator, '#').'(?P<'.$variable.'>'.$requirement.')';
        $this->variables[$variable] = $name;

        if (!$this->route->hasDefault($variable)) {
            $this->firstOptional = count($this->segments);
        }
    }

    protected function compileForSeparator($separator, $regexSeparator)
    {
    }

    private function getOptions()
    {
        $options = $this->route->getOptions();

        // compute some regexes
        $quoter = function ($a) { return preg_quote($a, '#'); };
        $options['segment_separators_regex'] = '(?:'.implode('|', array_map($quoter, $options['segment_separators'])).')';
        $options['variable_content_regex'] = '[^'.implode('', array_map($quoter, $options['segment_separators'])).']+?';

        return $options;
    }
}
