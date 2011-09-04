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
 * DumperInterface is the interface implemented by all translation dumpers.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
interface DumperInterface
{
    /**
     * Generates a string representation of the message catalogue
     *
     * @param MessageCatalogue $messages The message catalogue
     * @param string           $domain   The domain to dump
     *
     * @return string                    The string representation
     */
    function dump(MessageCatalogue $messages, $domain = 'messages');
}
