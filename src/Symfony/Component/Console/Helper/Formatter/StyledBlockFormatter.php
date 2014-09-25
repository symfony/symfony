<?php

namespace Symfony\Component\Console\Helper\Formatter;

/**
 * Formats a styled block
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class StyledBlockFormatter extends BlockFormatter
{
    protected $type;
    protected $prefix;

    /**
     * @param array|string $messages
     * @param string       $type
     * @param bool         $style
     * @param string       $prefix
     */
    public function __construct($messages, $type, $style, $prefix = '')
    {
        $this->type = $type;
        $this->prefix = $prefix;

        parent::__construct($messages, $style, false, 120);
    }

    /**
     * {@inheritdoc}
     */
    public function format()
    {
        $messages = array_values((array) $this->messages);
        $prefix = $this->prefix;

        $messages[0] = sprintf('[%s] %s', $this->type, $messages[0]);

        $messages = array_map(function ($value) use ($prefix) {
                return sprintf('%s%s', $prefix, $value);
            },
            $messages
        );

        $this->messages = $messages;

        return array(
            parent::format(),
            ''
        );
    }
}
