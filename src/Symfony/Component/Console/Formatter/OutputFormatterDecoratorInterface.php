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
 * The formatter decorator.
 *
 * @author Christian Neff <christian.neff@gmail.com>
 *
 * @api
 */
interface OutputFormatterDecoratorInterface
{
    /**
     * Decorates a text with the given style.
     *
     * @param string $text The text to style
     * @param OutputFormatterStyle $style The style to apply
     *
     * @return string
     */
    public function decorate($text, OutputFormatterStyle $style);
}
