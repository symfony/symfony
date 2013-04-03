<?php

namespace Symfony\Component\Console\Descriptor\Markdown\Document;

/**
 * Document block interface.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface BlockInterface
{
    /**
     * @return boolean
     */
    public function isEmpty();

    /**
     * Formats block output.
     *
     * @param Formatter $formatter
     *
     * @return string
     */
    public function format(Formatter $formatter);
}
