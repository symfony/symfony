<?php

namespace Symfony\Component\Console\Helper\Formatter;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface FormatterInterface
{
    /**
     * @return array|string
     */
    public function format();
}
