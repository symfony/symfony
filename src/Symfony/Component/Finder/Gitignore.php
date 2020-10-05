<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder;

/**
 * Gitignore matches against text.
 *
 * @author Ahmed Abdou <mail@ahmd.io>
 */
class Gitignore
{
    /**
     * Returns a regexp which is the equivalent of the gitignore pattern.
     *
     * @return string The regexp
     */
    public static function toRegex(string $gitignoreFileContent): string
    {
        $gitignoreFileContent = preg_replace('/^[^\\\r\n]*#.*/m', '', $gitignoreFileContent);
        $gitignoreLines = preg_split('/\r\n|\r|\n/', $gitignoreFileContent);

        $positives = [];
        $negatives = [];
        foreach ($gitignoreLines as $i => $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            if (1 === preg_match('/^!/', $line)) {
                $positives[$i] = null;
                $negatives[$i] = self::getRegexFromGitignore(preg_replace('/^!(.*)/', '${1}', $line), true);

                continue;
            }
            $negatives[$i] = null;
            $positives[$i] = self::getRegexFromGitignore($line);
        }

        $index = 0;
        $patterns = [];
        foreach ($positives as $pattern) {
            if (null === $pattern) {
                continue;
            }

            $negativesAfter = array_filter(\array_slice($negatives, ++$index));
            if ([] !== $negativesAfter) {
                $pattern .= sprintf('(?<!%s)', implode('|', $negativesAfter));
            }

            $patterns[] = $pattern;
        }

        return sprintf('/^((%s))$/', implode(')|(', $patterns));
    }

    private static function getRegexFromGitignore(string $gitignorePattern, bool $negative = false): string
    {
        $regex = '';
        $isRelativePath = false;
        // If there is a separator at the beginning or middle (or both) of the pattern, then the pattern is relative to the directory level of the particular .gitignore file itself
        $slashPosition = strpos($gitignorePattern, '/');
        if (false !== $slashPosition && \strlen($gitignorePattern) - 1 !== $slashPosition) {
            if (0 === $slashPosition) {
                $gitignorePattern = substr($gitignorePattern, 1);
            }

            $isRelativePath = true;
            $regex .= '^';
        }

        if ('/' === $gitignorePattern[\strlen($gitignorePattern) - 1]) {
            $gitignorePattern = substr($gitignorePattern, 0, -1);
        }

        $iMax = \strlen($gitignorePattern);
        for ($i = 0; $i < $iMax; ++$i) {
            $tripleChars = substr($gitignorePattern, $i, 3);
            if ('**/' === $tripleChars || '/**' === $tripleChars) {
                $regex .= '.*';
                $i += 2;
                continue;
            }

            $doubleChars = substr($gitignorePattern, $i, 2);
            if ('**' === $doubleChars) {
                $regex .= '.*';
                ++$i;
                continue;
            }
            if ('*/' === $doubleChars) {
                $regex .= '[^\/]*\/?[^\/]*';
                ++$i;
                continue;
            }

            $c = $gitignorePattern[$i];
            switch ($c) {
                case '*':
                    $regex .= $isRelativePath ? '[^\/]*' : '[^\/]*\/?[^\/]*';
                    break;
                case '/':
                case '.':
                case ':':
                case '(':
                case ')':
                case '{':
                case '}':
                    $regex .= '\\'.$c;
                    break;
                default:
                    $regex .= $c;
            }
        }

        if ($negative) {
            // a lookbehind assertion has to be a fixed width (it can not have nested '|' statements)
            return sprintf('%s$|%s\/$', $regex, $regex);
        }

        return '(?>'.$regex.'($|\/.*))';
    }
}
