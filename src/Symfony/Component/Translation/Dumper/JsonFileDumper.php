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

if (!defined('JSON_PRETTY_PRINT')) {
    define('JSON_PRETTY_PRINT', 128);
}

/**
 * JsonFileDumper generates an json formatted string representation of a message catalogue.
 *
 * @author singles
 */
class JsonFileDumper extends FileDumper
{
    /**
     * {@inheritDoc}
     */
    public function format(MessageCatalogue $messages, $domain = 'messages')
    {
        return json_encode($messages->all($domain), JSON_PRETTY_PRINT);
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtension()
    {
        return 'json';
    }
}
