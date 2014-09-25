<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $ret = array();

        $messages[0] = sprintf('[%s] %s', $this->type, $messages[0]);

        foreach ($messages as $key => &$message) {
            $ret[] = sprintf('%s%s', $prefix, $message);

            if (count($messages) > 1 && $key < count($message)) {
                $ret[] = $prefix;
            }
        }

        $this->messages = $ret;

        return array(
            parent::format(),
            ''
        );
    }
}
