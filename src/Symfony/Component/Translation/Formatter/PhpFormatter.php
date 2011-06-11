<?php

namespace Symfony\Component\Translation\Formatter;

class PhpFormatter implements FormatterInterface
{
    /**
     * {@inheritDoc}
     */
    public function format(array $messages)
    {
        $output = "<?php\nreturn ".var_export($messages, true).";";

        return $output;
    }
}
