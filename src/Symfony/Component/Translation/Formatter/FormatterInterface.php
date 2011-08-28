<?php

namespace Symfony\Component\Translation\Formatter;

/**
 * Interface for formatters
 */
interface FormatterInterface
{
    /**
     * Generates a string representation of the message format.
     *
     * @param array $messages
     * @return string
     */
    function format(array $messages);
}
