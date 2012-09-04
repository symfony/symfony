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
     * The default requirement as regular expression for each variable in the pattern when no custom
     * requirement is specified.
     */
    const DEFAULT_REQUIREMENT = '[^/]+';

    /**
     * {@inheritDoc}
     *
     * @throws \LogicException If a variable is referenced more than once
     */
    public function compile(Route $route)
    {
        $pattern = $route->getPattern();
        $tokens = array();
        $variables = array();
        $matches = array();
        $pos = 0;

        // match all variables enclosed in "{}" and iterate over them
        // but we only want to match the innermost variable in case of nested "{}", e.g. {foo{bar}}
        // this in ensured because \w does not match "{" or "}" itself
        preg_match_all('#\{\w+\}#', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $match) {
            $varName = substr($match[0][0], 1, -1);
            // get all static text preceding the current variable
            $precedingText = substr($pattern, $pos, $match[0][1] - $pos);
            $pos = $match[0][1] + strlen($match[0][0]);
            $precedingChar = strlen($precedingText) > 0 ? substr($precedingText, -1) : ''; // substr could otherwise return false
            $isSeparator = '' !== $precedingChar && false !== strpos(static::SEPARATORS, $precedingChar);

            if (in_array($varName, $variables)) {
                throw new \LogicException(sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $pattern, $varName));
            }

            if ($isSeparator && strlen($precedingText) > 1) {
                $this->addTextToken($tokens, substr($precedingText, 0, -1));
            } elseif (!$isSeparator && strlen($precedingText) > 0) {
                $this->addTextToken($tokens, $precedingText);
            }

            $regexp = $route->getRequirement($varName);
            if ('' == $regexp) {
                $regexp = static::DEFAULT_REQUIREMENT;
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
