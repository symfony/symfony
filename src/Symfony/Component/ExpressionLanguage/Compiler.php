<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Compiles a node to PHP code.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Compiler implements ResetInterface
{
    private string $source = '';
    private array $functions;

    public function __construct(array $functions)
    {
        $this->functions = $functions;
    }

    public function getFunction(string $name)
    {
        return $this->functions[$name];
    }

    /**
     * Gets the current PHP code after compilation.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return $this
     */
    public function reset(): static
    {
        $this->source = '';

        return $this;
    }

    /**
     * Compiles a node.
     *
     * @return $this
     */
    public function compile(Node\Node $node): static
    {
        $node->compile($this);

        return $this;
    }

    /**
     * @return string
     */
    public function subcompile(Node\Node $node)
    {
        $current = $this->source;
        $this->source = '';

        $node->compile($this);

        $source = $this->source;
        $this->source = $current;

        return $source;
    }

    /**
     * Adds a raw string to the compiled code.
     *
     * @return $this
     */
    public function raw(string $string): static
    {
        $this->source .= $string;

        return $this;
    }

    /**
     * Adds a quoted string to the compiled code.
     *
     * @return $this
     */
    public function string(string $value): static
    {
        $this->source .= sprintf('"%s"', addcslashes($value, "\0\t\"\$\\"));

        return $this;
    }

    /**
     * Returns a PHP representation of a given value.
     *
     * @return $this
     */
    public function repr(mixed $value): static
    {
        if (\is_int($value) || \is_float($value)) {
            if (false !== $locale = setlocale(\LC_NUMERIC, 0)) {
                setlocale(\LC_NUMERIC, 'C');
            }

            $this->raw($value);

            if (false !== $locale) {
                setlocale(\LC_NUMERIC, $locale);
            }
        } elseif (null === $value) {
            $this->raw('null');
        } elseif (\is_bool($value)) {
            $this->raw($value ? 'true' : 'false');
        } elseif (\is_array($value)) {
            $this->raw('[');
            $first = true;
            foreach ($value as $key => $value) {
                if (!$first) {
                    $this->raw(', ');
                }
                $first = false;
                $this->repr($key);
                $this->raw(' => ');
                $this->repr($value);
            }
            $this->raw(']');
        } else {
            $this->string($value);
        }

        return $this;
    }
}
