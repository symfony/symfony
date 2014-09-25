<?php

namespace Symfony\Component\Console\Helper\Formatter;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ListElementFormatter implements FormatterInterface
{
    protected $messages;

    /**
     * @param string|array $messages
     */
    public function __construct($messages)
    {
        $this->messages = $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function format()
    {
        $messages = array_values((array) $this->messages);

        $messages[0] = sprintf(' * %s', $messages[0]);

        foreach ($messages as $key => &$message) {
            if (0 === $key) {
                continue;
            }

            $message = sprintf('   %s', $message);
        }

        return array_merge($messages, array(''));
    }
}
