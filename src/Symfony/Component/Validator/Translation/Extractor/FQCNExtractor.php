<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Translation\Extractor;

/**
 * Extracts all FQCN from a given PHP source file.
 *
 * @author Webnet team <comptewebnet@webnet.fr>
 */
class FQCNExtractor
{
    /**
     * Absolute path to source file.
     *
     * @var string
     */
    private $source;

    /**
     * Found FQCN.
     *
     * @var array
     */
    private $classes = array();

    /**
     * @var string
     */
    private $currentNamespace = '';

    /**
     * Whether last token is T_NAMESPACE.
     *
     * @var bool
     */
    private $tNamespaceLastToken = false;

    /**
     * Whether last token is T_CLASS.
     *
     * @var bool
     */
    private $tClassLastToken = false;

    /**
     * Constructor.
     *
     * @param string $source
     */
    public function __construct(string $source)
    {
        if (!is_file($source)) {
            throw new \InvalidArgumentException($source.' is not a PHP source file.');
        }

        $this->source = $source;
    }

    /**
     * Return FQCN of classes defined in the source file.
     *
     * @return array
     */
    public function getDeclaredClasses(): array
    {
        $tokens = token_get_all(file_get_contents($this->source));

        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];

            if (isset($token[0]) && isset($token[1])) {
                // Treat only relevant tokens.
                $this->nextToken($token[0], $token[1]);
            } else {
                $this->tClassLastToken = false;
                $this->tNamespaceLastToken = false;
            }
        }

        return $this->classes;
    }

    /**
     * Treat next token.
     *
     * @param int    $tokenCode
     * @param string $tokenValue
     */
    private function nextToken(int $tokenCode, string $tokenValue): void
    {
        // skip whitespaces
        if (T_WHITESPACE === $tokenCode) {
            return;
        }

        // mark `namespace` reserved word
        if (T_NAMESPACE === $tokenCode) {
            $this->tNamespaceLastToken = true;
            $this->currentNamespace = '';

            return;
        }

        // add the next portion of namespace or end the namespace section
        if ($this->tNamespaceLastToken) {
            if (T_NS_SEPARATOR === $tokenCode || T_STRING === $tokenCode) {
                $this->currentNamespace .= $tokenValue;
            } else {
                $this->tNamespaceLastToken = false;
            }

            return;
        }

        // mark `class` reserved word
        if (T_CLASS === $tokenCode) {
            $this->tClassLastToken = true;

            return;
        }

        // save the class name
        if ($this->tClassLastToken) {
            if (T_STRING === $tokenCode) {
                // new class found
                $this->classes[] = $this->currentNamespace.'\\'.$tokenValue;
            }

            $this->tClassLastToken = false;

            return;
        }
    }
}
