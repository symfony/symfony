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
     * {@inheritDoc}
     *
     * @throws \LogicException  If a variable is referenced more than once
     * @throws \DomainException If a variable name is numeric because PHP raises an error for such
     *                          subpatterns in PCRE and thus would break matching, e.g. "(?<123>.+)".
     */
    public function compile(Route $route)
    {
        $pattern = $route->getPattern();
        $tokens = array();
        $variables = array();
        $matches = array();
        $pos = 0;

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

            if ('\\' === $precedingChar) {
                // When the variable placeholder is excaped with "\", e.g. "static\{static}", treat it as static text (without the escape char).
                // This allows "{static}" to be used in the route without being considered as variable.
                $this->addTextToken($tokens, substr($precedingText, 0, -1) . $match[0][0]);
                continue;
            }

            if (is_numeric($varName)) {
                throw new \DomainException(sprintf('Variable name "%s" cannot be numeric in route pattern "%s". Please use a different name.', $varName, $pattern));
            }
            if (in_array($varName, $variables)) {
                throw new \LogicException(sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $pattern, $varName));
            }

            if ($isSeparator && strlen($precedingText) > 1) {
                $this->addTextToken($tokens, substr($precedingText, 0, -1));
            } elseif (!$isSeparator && strlen($precedingText) > 0) {
                $this->addTextToken($tokens, $precedingText);
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
                $regexp = sprintf('[^/%s]+', '/' !== $nextSeparator && '' !== $nextSeparator ? preg_quote($nextSeparator, self::REGEX_DELIMITER) : '');
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
            $this->addTextToken($tokens, substr($pattern, $pos));
        }

        // find the first optional token
        $firstOptional = INF;
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if ('variable' === $token[0] && $route->hasDefault($token[3])) {
                $firstOptional = $i;
            } else {
                break;
            }
        }

        // compute the matching regexp
        $regexp = '';
        for ($i = 0, $nbToken = count($tokens); $i < $nbToken; $i++) {
            $regexp .= $this->computeRegexp($tokens, $i, $firstOptional);
        }

        return new CompiledRoute(
            'text' === $tokens[0][0] ? $tokens[0][1] : '',
            self::REGEX_DELIMITER.'^'.$regexp.'$'.self::REGEX_DELIMITER.'s',
            array_reverse($tokens),
            $variables
        );
    }

    /**
     * Adds a text token to the tokens array.
     *
     * @param array  $tokens The route tokens
     * @param string $text   The static text
     */
    private function addTextToken(array &$tokens, $text)
    {
        // when the last token is a text token, we can simply add the new text to it
        if (false !== end($tokens) && 'text' === $tokens[key($tokens)][0]) {
            $tokens[key($tokens)][1] .= $text;
        } else {
            $tokens[] = array('text', $text);
        }
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

        // first remove all (non-escaped) placeholders from the pattern so we can find the next real static character
        $pattern = preg_replace('#(?<!\\\\)\{\w+\}#', '', $pattern);
        if (preg_match('#^\\\\\\{\w+\}#', $pattern)) {
            // an escaped placeholder is the next static text which means the next char is "{" char and not "\"
            return false !== strpos(static::SEPARATORS, '{') ? '{' : '';
        }

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
                return sprintf('%s(?<%s>%s)?', preg_quote($token[1], self::REGEX_DELIMITER), $token[3], $token[2]);
            } else {
                $regexp = sprintf('%s(?<%s>%s)', preg_quote($token[1], self::REGEX_DELIMITER), $token[3], $token[2]);
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
