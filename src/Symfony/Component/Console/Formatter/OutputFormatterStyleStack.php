<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Formatter;

/**
 * @author: Jean-François Simon <contact@jfsimon.fr>
 */
class OutputFormatterStyleStack
{
    /**
     * @var array
     */
    private $foregrounds;

    /**
     * @var array
     */
    private $backgrounds;

    /**
     * @var array
     */
    private $options;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Resets stack (ie. empty internal arrays).
     */
    public function reset()
    {
        $this->foregrounds = array();
        $this->backgrounds = array();
        $this->options     = array();
    }

    /**
     * Pushes a style in the stack.
     *
     * @param OutputFormatterStyle $style
     */
    public function pushStyle(OutputFormatterStyle $style)
    {
        $foreground = $style->getForeground();
        if (null !== $foreground) {
            $this->foregrounds[] = $foreground;
        }

        $background = $style->getBackground();
        if (null !== $background) {
            $this->backgrounds[] = $background;
        }

        foreach ($style->getOptions() as $option) {
            if (!isset($this->options[$option])) {
                $this->options[$option] = 0;
            }
            $this->options[$option] += 1;
        }
    }

    /**
     * Pops a style from the stack.
     *
     * @param OutputFormatterStyle $style
     *
     * @throws \InvalidArgumentException  When style tags incorrectly nested
     */
    public function popStyle(OutputFormatterStyle $style)
    {
        $this->popArrayCode($this->foregrounds, $style->getForeground());
        $this->popArrayCode($this->backgrounds, $style->getBackground());

        foreach ($style->getOptions() as $option) {
            if (!isset($this->options[$option])) {
                throw new \InvalidArgumentException('Unexpected style "'.$option.'" option end.');
            }

            $this->options[$option] -= 1;
            if (0 === $this->options[$option]) {
                unset($this->options[$option]);
            }
        }
    }

    /**
     * Computes current style with stacks top codes.
     *
     * @return OutputFormatterStyle
     */
    public function getCurrentStyle()
    {
        return new OutputFormatterStyle(
            end($this->foregrounds) ?: null,
            end($this->backgrounds) ?: null,
            array_keys($this->options)
        );
    }

    /**
     * Pops a color from a stack.
     *
     * @param array  $stack  An array of color names
     * @param string $color  A color name
     *
     * @throws \InvalidArgumentException  When poped color is not the expected one
     */
    private function popArrayCode(&$stack, $color)
    {
        if (null === $color) {
            return;
        }

        $current = end($stack);
        if ($current !== $color) {
            throw new \InvalidArgumentException('Expected style "'.$current.'" color end but "'.$color.'" end found.');
        }

        array_pop($stack);
    }
}
