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
 * @author Tobias Schultze <http://tobion.de>
 */
class RouteCompiler implements RouteCompilerInterface
{
    const REGEX_DELIMITER = '#';

    /**
     * This string defines the characters that are automatically considered separators in front of
     * implicit optional placeholders (with default and no static text following). Such a single separator
     * can be left out together with the optional placeholder from matching and generating URLs.
     */
    const SEPARATORS = '/,;.:-_~+*=@|';

    /**
     * {@inheritDoc}
     *
     * @throws \LogicException  If a variable is referenced more than once
     * @throws \DomainException If a variable name is numeric because PHP raises an error for such
     *                          subpatterns in PCRE and thus would break matching, e.g. "(?P<123>.+)".
     */
    public function compile(Route $route)
    {
        $staticPrefix = null;
        $hostnameVariables = array();
        $pathVariables = array();
        $variables = array();
        $tokens = array();
        $regex = null;
        $hostnameRegex = null;
        $hostnameTokens = array();

        if ('' !== $hostnamePattern = $route->getHostnamePattern()) {
            $result = $this->compilePattern($route, $hostnamePattern, true);

            $hostnameVariables = $result['variables'];
            $variables = array_merge($variables, $hostnameVariables);

            $hostnameTokens = $result['tokens'];
            $hostnameRegex = $result['regex'];
        }

        $pattern = $route->getPattern();

        $result = $this->compilePattern($route, $pattern, false);

        $staticPrefix = $result['staticPrefix'];

        $pathVariables = $result['variables'];
        $variables = array_merge($variables, $pathVariables);

        $tokens = $result['tokens'];
        $regex = $result['regex'];

        return new CompiledRoute(
            $staticPrefix,
            $regex,
            $tokens,
            $pathVariables,
            $hostnameRegex,
            $hostnameTokens,
            $hostnameVariables,
            array_unique($variables)
        );
    }

    private function compilePattern(Route $route, $pattern, $isHostname)
    {
        $tokens = array();
        $variables = array();
        $matches = array();
        $pos = 0;
        $defaultSeparator = $isHostname ? '.' : '/';

        // Match all variables enclosed in "{}" and iterate over them. But we only want to match the innermost variable
        // in case of nested "{}", e.g. {foo{bar}}. This in ensured because \w does not match "{" or "}" itself.
        preg_match_all('#\{\w+\}#', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $match) {
            $varName = substr($match[0][0], 1, -1);
            // get all static text preceding the current variable
            $precedingText = substr($pattern, $pos, $match[0][1] - $pos);
            $pos = $match[0][1] + strlen($match[0][0]);
            $precedingChar = strlen($precedingText) > 0 ? substr($precedingText, -1) : '';
            $isSeparator = '' !== $precedingChar && false !== strpos(static::SEPARATORS, $precedingChar);

            if (is_numeric($varName)) {
                throw new \DomainException(sprintf('Variable name "%s" cannot be numeric in route pattern "%s". Please use a different name.', $varName, $pattern));
            }
            if (in_array($varName, $variables)) {
                throw new \LogicException(sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $pattern, $varName));
            }

            if ($isSeparator && strlen($precedingText) > 1) {
                $tokens[] = array('text', substr($precedingText, 0, -1));
            } elseif (!$isSeparator && strlen($precedingText) > 0) {
                $tokens[] = array('text', $precedingText);
            }

            $regexp = $route->getRequirement($varName);
            if (null === $regexp) {
                $followingPattern = (string) substr($pattern, $pos);
                // Find the next static character after the variable that functions as a separator. By default, this separator and '/'
                // are disallowed for the variable. This default requirement makes sure that optional variables can be matched at all
                // and that the generating-matching-combination of URLs unambiguous, i.e. the params used for generating the URL are
                // the same that will be matched. Example: new Route('/{page}.{_format}', array('_format' => 'html'))
                // If {page} would also match the separating dot, {_format} would never match as {page} will eagerly consume everything.
                // Also even if {_format} was not optional the requirement prevents that {page} matches something that was originally
                // part of {_format} when generating the URL, e.g. _format = 'mobile.html'.
                $nextSeparator = $this->findNextSeparator($followingPattern);
                $regexp = sprintf(
                    '[^%s%s]+',
                    preg_quote($defaultSeparator, self::REGEX_DELIMITER),
                    $defaultSeparator !== $nextSeparator && '' !== $nextSeparator ? preg_quote($nextSeparator, self::REGEX_DELIMITER) : ''
                );
                if (('' !== $nextSeparator && !preg_match('#^\{\w+\}#', $followingPattern)) || '' === $followingPattern) {
                    // When we have a separator, which is disallowed for the variable, we can optimize the regex with a possessive
                    // quantifier. This prevents useless backtracking of PCRE and improves performance by 20% for matching those patterns.
                    // Given the above example, there is no point in backtracking into {page} (that forbids the dot) when a dot must follow
                    // after it. This optimization cannot be applied when the next char is no real separator or when the next variable is
                    // directly adjacent, e.g. '/{x}{y}'.
                    $regexp .= '+';
                }
            }

            $tokens[] = array('variable', $isSeparator ? $precedingChar : '', $regexp, $varName);
            $variables[] = $varName;
        }

        if ($pos < strlen($pattern)) {
            $tokens[] = array('text', substr($pattern, $pos));
        }

        // find the first optional token
        $firstOptional = INF;
        if (!$isHostname) {
            for ($i = count($tokens) - 1; $i >= 0; $i--) {
                $token = $tokens[$i];
                if ('variable' === $token[0] && $route->hasDefault($token[3])) {
                    $firstOptional = $i;
                } else {
                    break;
                }
            }
        }

        // compute the matching regexp
        $regexp = '';
        for ($i = 0, $nbToken = count($tokens); $i < $nbToken; $i++) {
            $regexp .= $this->computeRegexp($tokens, $i, $firstOptional);
        }

        return array(
            'staticPrefix' => 'text' === $tokens[0][0] ? $tokens[0][1] : '',
            'regex' => self::REGEX_DELIMITER.'^'.$regexp.'$'.self::REGEX_DELIMITER.'s',
            'tokens' => array_reverse($tokens),
            'variables' => $variables,
        );
    }

    /**
     * Performs lexical and syntactic analysis of the pattern and returns the parse tree
     * consisting of tokens.
     *
     * The array is a tree when optional parts enclosed with parentheses are used
     * that can potentially be nested at any deepth.
     *
     * @param string  $pattern     The route pattern or subpattern for an optional part
     * @param Boolean $isHostname  Whether it is the pattern for the hostname or path
     * @param string  $fullPattern The full pattern used for better exception messages
     * @param integer $parentPos   The parsing position of the parent call
     *
     * @return array The parse tree of tokens
     *
     * @throws \LogicException If the pattern is invalid
     */
    public static function parsePattern($pattern, $isHostname = false, $fullPattern = '', $parentPos = 0)
    {
        $tokens = array();
        $matches = array();
        $pos = 0;

        if ('' === $fullPattern) {
            $fullPattern = $pattern;
        }

        // '#\{.*?\}|\((?:[^()]++|(?R))*\)#'
        // '#(?<!\\\\)\{.*?(?<!\\\\)\}|(?<!\\\\)\((?:[^()]++|((?<=\\\\)[()])++|(?R))*(?<!\\\\)\)#'
        preg_match_all('#(?<!\\\\)\{.*?(?<!\\\\)\}|(?<!\\\\)\((?:[^()]++|((?<=\\\\)[()])++|(?R))*(?<!\\\\)\)#', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $match) {
            // get all static text preceding the current variable or optional part
            $precedingText = substr($pattern, $pos, $match[0][1] - $pos);
            if (strlen($precedingText) > 0) {
                self::addTextToken($tokens, $precedingText, $fullPattern, $parentPos + $pos);
            }

            $content = substr($match[0][0], 1, -1);
            if ('{' === $match[0][0][0]) {
                if ('' === $content || preg_match('#[^\w]#', $content)) {
                    throw new \LogicException(sprintf('Variable name "%s" cannot be empty and must only contain letters, digits and underscores in route pattern "%s" at position %s.', $content, $fullPattern, $parentPos + $match[0][1]));
                }
                if (is_numeric($content)) {
                    throw new \DomainException(sprintf('Variable name "%s" cannot be numeric in route pattern "%s" at position %s. Please use a different name.', $content, $fullPattern, $parentPos + $match[0][1]));
                }
                // add variable name as token
                $tokens[] = array(CompiledRoute::VARIABLE_TOKEN, $content);
            } else {
                // recursively tokenize an optional part that can itself have text/variable/optional tokens
                $subTokens = self::parsePattern($content, $isHostname, $fullPattern, $parentPos + $match[0][1] + 1);
                if (!self::containsVariableToken($subTokens)) {
                    // TODO explain why
                    throw new \LogicException(sprintf('The optional part "%s" must contain at least one variable placeholder in route pattern "%s" at position %s.', $match[0][0], $fullPattern, $parentPos + $match[0][1]));
                }
                $tokens[] = array(CompiledRoute::OPTIONAL_TOKEN, $subTokens);
            }

            $pos = $match[0][1] + strlen($match[0][0]);
        }

        if ($pos < strlen($pattern)) {
            // add all text behind the last variable or optional part
            self::addTextToken($tokens, substr($pattern, $pos), $fullPattern, $parentPos + $pos);
        }

        return $tokens;
    }

    /**
     * Adds a text token to the tokens array.
     *
     * @param array   $tokens  The tokens
     * @param string  $text    The static text
     * @param string  $pattern The pattern used for a proper exception message
     * @param integer $pos     The position of the text in the pattern
     *
     * @throws \LogicException If there is an unescaped parentheses or curly brace in the text
     */
    private static function addTextToken(array &$tokens, $text, $pattern, $pos)
    {
        $matches = array();
        if (preg_match('#(?<!\\\\)[{}()]#', $text, $matches, PREG_OFFSET_CAPTURE)) {
            throw new \LogicException(sprintf('There is an unescaped "%s" in route pattern "%s" at position %s.', $matches[0][0], $pattern, $pos + $matches[0][1]));
        }

        $tokens[] = array(CompiledRoute::TEXT_TOKEN, $text);
    }

    /**
     * Transforms the token array so that implicit optional variables are
     * correctly represented in the parse tree.
     *
     * Variables are optional if they have a default value and are at the end
     * of the pattern. A single separating character in front of the optional variable
     * can also be left out from matching and generating URLs. See self::SEPARATORS.
     *
     * @param array $tokens   The array of tokens
     * @param array $defaults The array of defaults for the variables
     *
     * @return array The corrected array of tokens
     */
    public static function convertImplicitOptionals(array $tokens, array $defaults)
    {
        for($i = count($tokens)-1; $i >= 0; $i--) {
            if (CompiledRoute::VARIABLE_TOKEN !== $tokens[$i][0] || !array_key_exists($tokens[$i][1], $defaults)) {
                return $tokens;
            }

            $tokens[$i] = array(CompiledRoute::OPTIONAL_TOKEN, array($tokens[$i]), array('implicit' => true));

            if (isset($tokens[$i+1])) {
                $tokens[$i][1][] = $tokens[$i+1];
                unset($tokens[$i+1]);
            }

            // if there is a preceeding separating char, move it into the optional token (except the starting slash which is required)
            if (isset($tokens[$i-1]) && CompiledRoute::TEXT_TOKEN === $tokens[$i-1][0] && !(1 === $i && '/' === $tokens[$i-1][1])) {
                $separator = substr($tokens[$i-1][1], -1);
                if (false !== strpos(static::SEPARATORS, $separator)) {
                    if (1 === strlen($tokens[$i-1][1])) {
                        array_unshift($tokens[$i][1], $tokens[$i-1]);
                        $tokens[$i-1] = $tokens[$i];
                        unset($tokens[$i]);
                        $i--;
                    } else {
                        $tokens[$i-1][1] = substr($tokens[$i-1][1], 0, -1);
                        array_unshift($tokens[$i][1], array(CompiledRoute::TEXT_TOKEN, $separator));
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * Checks whether there is a variable token in the tokens array as direct child.
     *
     * @param array $tokens The tokens
     *
     * @return Boolean
     */
    private static function containsVariableToken(array $tokens)
    {
        foreach ($tokens as $token) {
            if (CompiledRoute::VARIABLE_TOKEN !== $token[0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Computes the default requirement for each variable placeholder or uses the
     * given custom regex from the requirements array.
     *
     * @param array  $tokens       The tree of tokens
     * @param array  $requirements The custom requirements
     * @param string $pattern      The pattern used for a proper exception message
     * @param array  $variables    The variables and requirements computed so far
     *
     * @return array An array indexed by the variable names and the requirement regex as value
     *
     * @throws \LogicException If a variable is referenced more than once
     */
    private static function computeRequirements(array $tokens, array $requirements, $pattern, array &$variables = array())
    {
        foreach ($tokens as $token) {
            if (CompiledRoute::VARIABLE_TOKEN === $token[0]) {
                if (isset($variables[$token[1]])) {
                    throw new \LogicException(sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $pattern, $token[1]));
                }

                if (array_key_exists($token[1], $requirements)) {
                    $variables[$token[1]] = $requirements[$token[1]];
                } else {
                    $variables[$token[1]] = 'TODO';
                }
            } elseif (CompiledRoute::OPTIONAL_TOKEN === $token[0]) {
                self::computeRequirements($token[1], $requirements, $pattern, $variables);
            }
        }

        return $variables;
    }

    /**
     * Returns the next static character in the Route pattern that will serve as a separator.
     *
     * @param string $pattern The route pattern
     *
     * @return string The next static character that functions as separator (or empty string when none available)
     */
    private function findNextSeparator($pattern)
    {
        if ('' == $pattern) {
            // return empty string if pattern is empty or false (false which can be returned by substr)
            return '';
        }
        // first remove all placeholders from the pattern so we can find the next real static character
        $pattern = preg_replace('#\{\w+\}#', '', $pattern);

        return isset($pattern[0]) && false !== strpos(static::SEPARATORS, $pattern[0]) ? $pattern[0] : '';
    }

    /**
     * Computes the regexp used to match a specific token. It can be static text or a subpattern.
     *
     * @param array   $tokens        The route tokens
     * @param integer $index         The index of the current token
     * @param integer $firstOptional The index of the first optional token
     *
     * @return string The regexp pattern for a single token
     */
    private function computeRegexp(array $tokens, $index, $firstOptional)
    {
        $token = $tokens[$index];
        if ('text' === $token[0]) {
            // Text tokens
            return preg_quote($token[1], self::REGEX_DELIMITER);
        } else {
            // Variable tokens
            if (0 === $index && 0 === $firstOptional) {
                // When the only token is an optional variable token, the separator is required
                return sprintf('%s(?P<%s>%s)?', preg_quote($token[1], self::REGEX_DELIMITER), $token[3], $token[2]);
            } else {
                $regexp = sprintf('%s(?P<%s>%s)', preg_quote($token[1], self::REGEX_DELIMITER), $token[3], $token[2]);
                if ($index >= $firstOptional) {
                    // Enclose each optional token in a subpattern to make it optional.
                    // "?:" means it is non-capturing, i.e. the portion of the subject string that
                    // matched the optional subpattern is not passed back.
                    $regexp = "(?:$regexp";
                    $nbTokens = count($tokens);
                    if ($nbTokens - 1 == $index) {
                        // Close the optional subpatterns
                        $regexp .= str_repeat(")?", $nbTokens - $firstOptional - (0 === $firstOptional ? 1 : 0));
                    }
                }

                return $regexp;
            }
        }
    }
}
