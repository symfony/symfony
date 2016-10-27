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
     * optional placeholders (with default and no static text following). Such a single separator
     * can be left out together with the optional placeholder from matching and generating URLs.
     */
    const SEPARATORS = '/,;.:-_~+*=@|';

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException  If a variable is referenced more than once
     * @throws \DomainException If a variable name is numeric because PHP raises an error for such
     *                          subpatterns in PCRE and thus would break matching, e.g. "(?P<123>.+)".
     */
    public static function compile(Route $route)
    {
        $hostVariables = array();
        $variables = array();
        $hostRegex = null;
        $hostRegexVariablesAliases = array();
        $hostTokens = array();

        if ('' !== $host = $route->getHost()) {
            $result = self::compilePattern($route, $host, true);

            $hostVariables = $result['variables'];
            $variables = $hostVariables;

            $hostTokens = $result['tokens'];
            $hostRegex = $result['regex'];
            $hostRegexVariablesAliases = $result['regex_variables_aliases'];
        }

        $path = $route->getPath();

        $result = self::compilePattern($route, $path, false);

        $staticPrefix = $result['staticPrefix'];

        $pathVariables = $result['variables'];
        $variables = array_merge($variables, $pathVariables);

        $tokens = $result['tokens'];
        $regex = $result['regex'];
        $regexVariablesAliases = $result['regex_variables_aliases'];

        return new CompiledRoute(
            $staticPrefix,
            $regex,
            $tokens,
            $pathVariables,
            $hostRegex,
            $hostTokens,
            $hostVariables,
            array_unique($variables),
            $regexVariablesAliases,
            $hostRegexVariablesAliases
        );
    }

    private static function compilePattern(Route $route, $pattern, $isHost)
    {
        $tokens = array();
        $variables = array();
        $matches = array();
        $pos = 0;
        $defaultSeparator = $isHost ? '.' : '/';

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
                $nextSeparator = self::findNextSeparator($followingPattern);
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

        $tokensCount = count($tokens);

        // find the first optional token
        $firstOptional = PHP_INT_MAX;
        if (!$isHost) {
            for ($i = $tokensCount - 1; $i >= 0; --$i) {
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
        $regexpVariablesAliases = array();
        for ($i = 0, $nbToken = $tokensCount; $i < $nbToken; ++$i) {
            $token = $tokens[$i];
            switch ($token[0]) {
                case 'text':
                    $regexp .= self::computeRegexpForTextToken($token);
                    break;
                case 'variable':
                    list($tokenRegexp, $regexpVariableName) = self::computeRegexpForVariableToken($token, $i, $tokensCount, $firstOptional, $variables);
                    $regexp .= $tokenRegexp;

                    $variableName = $token[3];
                    if ($regexpVariableName !== $variableName) {
                        $regexpVariablesAliases[$regexpVariableName] = $variableName;
                    }

                    break;
                default:
                    throw new \LogicException('The token type should be "text" or "variable".');
            }
        }

        return array(
            'staticPrefix' => 'text' === $tokens[0][0] ? $tokens[0][1] : '',
            'regex' => self::REGEX_DELIMITER.'^'.$regexp.'$'.self::REGEX_DELIMITER.'s'.($isHost ? 'i' : ''),
            'tokens' => array_reverse($tokens),
            'variables' => $variables,
            'regex_variables_aliases' => $regexpVariablesAliases,
        );
    }

    /**
     * Returns the next static character in the Route pattern that will serve as a separator.
     *
     * @param string $pattern The route pattern
     *
     * @return string The next static character that functions as separator (or empty string when none available)
     */
    private static function findNextSeparator($pattern)
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
     * Computes the regexp used to match a static text token.
     *
     * @param array $token The static text token
     *
     * @return string The regexp pattern of the token
     */
    private static function computeRegexpForTextToken(array $token)
    {
        return preg_quote($token[1], self::REGEX_DELIMITER);
    }

    /**
     * Computes the regexp used to match a subpattern token.
     *
     * @param array $token         The subpattern token
     * @param int   $index         The index of the token
     * @param int   $tokensCount   The total number of tokens of the route
     * @param int   $firstOptional The index of the first optional token
     * @param array $variables     All the variables names of the route
     *
     * @return array An array containing the regexp pattern of the token, and the variable name that is used in this regexp pattern
     */
    private static function computeRegexpForVariableToken(array $token, $index, $tokensCount, $firstOptional, array $variables)
    {
        $variableName = $token[3];
        // 32 is the maximum length for a PCRE subpattern name  => http://pcre.org/current/doc/html/pcre2pattern.html#SEC16
        if (strlen($variableName) > 32) {
            $i = 0;
            do {
                $variableName = sprintf('variableAlias%s', ++$i);
            } while (in_array($variableName, $variables));
        }

        if (0 === $index && 0 === $firstOptional) {
            // When the only token is an optional variable token, the separator is required
            $regexp = sprintf('%s(?P<%s>%s)?', preg_quote($token[1], self::REGEX_DELIMITER), $variableName, $token[2]);
        } else {
            $regexp = sprintf('%s(?P<%s>%s)', preg_quote($token[1], self::REGEX_DELIMITER), $variableName, $token[2]);
            if ($index >= $firstOptional) {
                // Enclose each optional token in a subpattern to make it optional.
                // "?:" means it is non-capturing, i.e. the portion of the subject string that
                // matched the optional subpattern is not passed back.
                $regexp = "(?:$regexp";
                if ($tokensCount - 1 == $index) {
                    // Close the optional subpatterns
                    $regexp .= str_repeat(')?', $tokensCount - $firstOptional - (0 === $firstOptional ? 1 : 0));
                }
            }
        }

        return array(
            $regexp,
            $variableName,
        );
    }
}
