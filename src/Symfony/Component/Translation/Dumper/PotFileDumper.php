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
 * PotFileDumper generates a gettext formatted string representation of a message catalogue.
 *
 * @author Adam Prager <prager.adam87@gmail.com>
 */
class PotFileDumper extends PoFileDumper
{
    /**
     * {@inheritDoc}
     */
    public function dump(MessageCatalogue $messages, $options = array())
    {
        if (!array_key_exists('path', $options)) {
            throw new \InvalidArgumentException('The file dumper need a path options.');
        }

        // save a file for each domain
        foreach ($messages->getDomains() as $domain) {
            $file = $domain.'.'.$this->getExtension();
            // backup
            $fullpath = $options['path'].'/'.$file;
            if (file_exists($fullpath)) {
                copy($fullpath, $fullpath.'~');
            }
            // save file
            file_put_contents($fullpath, $this->format($messages, $domain));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function format(MessageCatalogue $messages, $domain = 'messages')
    {
        return parent::format($messages, $domain);
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtension()
    {
        return 'pot';
    }
}
