<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Formatter;

use Symfony\Component\Debug\Exception\FlattenException;

interface FormatterInterface
{
    /**
     * Sets the charset used by exception messages.
     *
     * @param string $charset the charset used by exception messages
     */
    public function setCharset($string);

    /**
     * Gets the MIME type of the content returned by this formatter.
     *
     * @return string a MIME-type, possibly with a charset parameter
     */
    public function getContentType();

    /**
     * Gets the formatted exception.
     *
     * @param FlattenException the exception to format
     * @param bool             whether to output detailed debug information
     *
     * @return string the formatted exception
     */
    public function getContent(FlattenException $exception, $debug);
}
