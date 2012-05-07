<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * PoFileDumper generates a gettext formatted string representation of a message catalogue.
 *
 * @author Stealth35
 */
class PoFileDumper extends FileDumper
{
    /**
     * {@inheritDoc}
     */
    public function format(MessageCatalogue $messages, $domain = 'messages')
    {
        $output = '';

        foreach ($messages->all($domain) as $source => $target) {
            $output .= sprintf("msgid \"%s\"\n", $this->escape($source));
            $output .= sprintf("msgstr \"%s\"\n\n", $this->escape($target));
        }

        return $output;
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtension()
    {
        return 'po';
    }

    private function escape($str)
    {
        return addcslashes($str, "\0..\37\42\134");
    }
}
