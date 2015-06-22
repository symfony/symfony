<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Formatter;

/**
 * MessageFormatterInterface.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 */
interface MessageFormatterInterface
{
    /**
     * Formats a lozalized message pattern with given arguments.
     *
     * @param string $locale    The message locale
     * @param string $id        The message id (may also be an object that can be cast to string)
     * @param array  $arguments An array of parameters for the message
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function format($locale, $id, array $arguments = array());
}
