<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv;

use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Dotenv\Exception\FormatExceptionContext;
use Symfony\Component\Dotenv\Exception\PathException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * Manages .env files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Dotenv
{
    const VARNAME_REGEX = '(?i:[A-Z][A-Z0-9_]*+)';
    const STATE_VARNAME = 0;
    const STATE_VALUE = 1;

    private $path;
    private $cursor;
    private $lineno;
    private $data;
    private $end;
    private $state;
    private $values;

    /**
     * Loads one or several .env files.
     *
     * @param string    $path  A file to load
     * @param ...string $paths A list of additional files to load
     *
     * @throws FormatException when a file has a syntax error
     * @throws PathException   when a file does not exist or is not readable
     */
    public function load($path/*, ...$paths*/)
    {
        // func_get_args() to be replaced by a variadic argument for Symfony 4.0
        foreach (func_get_args() as $path) {
            if (!is_readable($path) || is_dir($path)) {
                throw new PathException($path);
            }

            $this->populate($this->parse(file_get_contents($path), $path));
        }
    }

    /**
     * Sets values as environment variables (via putenv, $_ENV, and $_SERVER).
     *
     * Note that existing environment variables are never overridden.
     *
     * @param array $values An array of env variables
     */
    public function populate($values)
    {
        foreach ($values as $name => $value) {
            if (isset($_ENV[$name]) || isset($_SERVER[$name]) || false !== getenv($name)) {
                continue;
            }

            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    /**
     * Parses the contents of an .env file.
     *
     * @param string $data The data to be parsed
     * @param string $path The original file name where data where stored (used for more meaningful error messages)
     *
     * @return array An array of env variables
     *
     * @throws FormatException when a file has a syntax error
     */
    public function parse($data, $path = '.env')
    {
        $this->path = $path;
        $this->data = str_replace(array("\r\n", "\r"), "\n", $data);
        $this->lineno = 1;
        $this->cursor = 0;
        $this->end = strlen($this->data);
        $this->state = self::STATE_VARNAME;
        $this->values = array();
        $name = $value = '';

        $this->skipEmptyLines();

        while ($this->cursor < $this->end) {
            switch ($this->state) {
                case self::STATE_VARNAME:
                    $name = $this->lexVarname();
                    $this->state = self::STATE_VALUE;
                    break;

                case self::STATE_VALUE:
                    $this->values[$name] = $this->lexValue();
                    $this->state = self::STATE_VARNAME;
                    break;
            }
        }

        if (self::STATE_VALUE === $this->state) {
            $this->values[$name] = '';
        }

        try {
            return $this->values;
        } finally {
            $this->values = array();
            $this->data = null;
            $this->path = null;
        }
    }

    private function lexVarname()
    {
        // var name + optional export
        if (!preg_match('/(export[ \t]++)?('.self::VARNAME_REGEX.')/A', $this->data, $matches, 0, $this->cursor)) {
            throw $this->createFormatException('Invalid character in variable name');
        }
        $this->moveCursor($matches[0]);

        if ($this->cursor === $this->end || "\n" === $this->data[$this->cursor] || '#' === $this->data[$this->cursor]) {
            if ($matches[1]) {
                throw $this->createFormatException('Unable to unset an environment variable');
            }

            throw $this->createFormatException('Missing = in the environment variable declaration');
        }

        if (' ' === $this->data[$this->cursor] || "\t" === $this->data[$this->cursor]) {
            throw $this->createFormatException('Whitespace are not supported after the variable name');
        }

        if ('=' !== $this->data[$this->cursor]) {
            throw $this->createFormatException('Missing = in the environment variable declaration');
        }
        ++$this->cursor;

        return $matches[2];
    }

    private function lexValue()
    {
        if (preg_match('/[ \t]*+(?:#.*)?$/Am', $this->data, $matches, null, $this->cursor)) {
            $this->moveCursor($matches[0]);
            $this->skipEmptyLines();

            return '';
        }

        if (' ' === $this->data[$this->cursor] || "\t" === $this->data[$this->cursor]) {
            throw $this->createFormatException('Whitespace are not supported before the value');
        }

        $value = '';
        $singleQuoted = false;
        $notQuoted = false;
        if ("'" === $this->data[$this->cursor]) {
            $singleQuoted = true;
            ++$this->cursor;
            while ("\n" !== $this->data[$this->cursor]) {
                if ("'" === $this->data[$this->cursor]) {
                    if ($this->cursor + 1 === $this->end) {
                        break;
                    }
                    if ("'" !== $this->data[$this->cursor + 1]) {
                        break;
                    }

                    ++$this->cursor;
                }
                $value .= $this->data[$this->cursor];
                ++$this->cursor;

                if ($this->cursor === $this->end) {
                    throw $this->createFormatException('Missing quote to end the value');
                }
            }
            if ("\n" === $this->data[$this->cursor]) {
                throw $this->createFormatException('Missing quote to end the value');
            }
            ++$this->cursor;
        } elseif ('"' === $this->data[$this->cursor]) {
            ++$this->cursor;
            while ('"' !== $this->data[$this->cursor] || ('\\' === $this->data[$this->cursor - 1] && '\\' !== $this->data[$this->cursor - 2])) {
                $value .= $this->data[$this->cursor];
                ++$this->cursor;

                if ($this->cursor === $this->end) {
                    throw $this->createFormatException('Missing quote to end the value');
                }
            }
            if ("\n" === $this->data[$this->cursor]) {
                throw $this->createFormatException('Missing quote to end the value');
            }
            ++$this->cursor;
            $value = str_replace(array('\\\\', '\\"', '\r', '\n'), array('\\', '"', "\r", "\n"), $value);
        } else {
            $notQuoted = true;
            $prevChr = $this->data[$this->cursor - 1];
            while ($this->cursor < $this->end && "\n" !== $this->data[$this->cursor] && !((' ' === $prevChr || "\t" === $prevChr) && '#' === $this->data[$this->cursor])) {
                $value .= $prevChr = $this->data[$this->cursor];
                ++$this->cursor;
            }
            $value = rtrim($value);
        }

        $this->skipEmptyLines();

        $currentValue = $value;
        if (!$singleQuoted) {
            $value = $this->resolveVariables($value);
            $value = $this->resolveCommands($value);
        }

        if ($notQuoted && $currentValue == $value && preg_match('/\s+/', $value)) {
            throw $this->createFormatException('A value containing spaces must be surrounded by quotes');
        }

        return $value;
    }

    private function skipEmptyLines()
    {
        if (preg_match('/(?:\s*+(?:#[^\n]*+)?+)++/A', $this->data, $match, null, $this->cursor)) {
            $this->moveCursor($match[0]);
        }
    }

    private function resolveCommands($value)
    {
        if (false === strpos($value, '$')) {
            return $value;
        }

        $regex = '/
            (\\\\)?               # escaped with a backslash?
            \$
            (?<cmd>
                \(                # require opening parenthesis
                ([^()]|\g<cmd>)+  # allow any number of non-parens, or balanced parens (by nesting the <cmd> expression recursively)
                \)                # require closing paren
            )
        /x';

        return preg_replace_callback($regex, function ($matches) {
            if ('\\' === $matches[1]) {
                return substr($matches[0], 1);
            }

            if ('\\' === DIRECTORY_SEPARATOR) {
                throw new \LogicException('Resolving commands is not supported on Windows.');
            }

            if (!class_exists(Process::class)) {
                throw new \LogicException('Resolving commands requires the Symfony Process component.');
            }

            $process = new Process('echo '.$matches[0]);
            $process->inheritEnvironmentVariables(true);
            $process->setEnv($this->values);
            try {
                $process->mustRun();
            } catch (ProcessException $e) {
                throw $this->createFormatException(sprintf('Issue expanding a command (%s)', $process->getErrorOutput()));
            }

            return preg_replace('/[\r\n]+$/', '', $process->getOutput());
        }, $value);
    }

    private function resolveVariables($value)
    {
        if (false === strpos($value, '$')) {
            return $value;
        }

        $regex = '/
            (\\\\)?                    # escaped with a backslash?
            \$
            (?!\()                     # no opening parenthesis
            (\{)?                      # optional brace
            ('.self::VARNAME_REGEX.')  # var name
            (\})?                      # optional closing brace
        /x';

        $value = preg_replace_callback($regex, function ($matches) {
            if ('\\' === $matches[1]) {
                return substr($matches[0], 1);
            }

            if ('{' === $matches[2] && !isset($matches[4])) {
                throw $this->createFormatException('Unclosed braces on variable expansion');
            }

            $name = $matches[3];
            $value = isset($this->values[$name]) ? $this->values[$name] : (isset($_ENV[$name]) ? isset($_ENV[$name]) : (string) getenv($name));

            if (!$matches[2] && isset($matches[4])) {
                $value .= '}';
            }

            return $value;
        }, $value);

        // unescape $
        return str_replace('\\$', '$', $value);
    }

    private function moveCursor($text)
    {
        $this->cursor += strlen($text);
        $this->lineno += substr_count($text, "\n");
    }

    private function createFormatException($message)
    {
        return new FormatException($message, new FormatExceptionContext($this->data, $this->path, $this->lineno, $this->cursor));
    }
}
