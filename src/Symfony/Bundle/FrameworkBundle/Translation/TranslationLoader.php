<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\MessageCatalogue;

@trigger_error(sprintf('The class "%s" is deprecated since Symfony 3.4 and will be removed in 4.0. Use "%s" instead. ', TranslationLoader::class, TranslationReader::class), E_USER_DEPRECATED);

/**
 * @deprecated since version 3.4 and will be removed in 4.0. Use Symfony\Component\Translation\Reader\TranslationReader instead
 */
class TranslationLoader extends TranslationReader
{
    /**
     * Loads translation messages from a directory to the catalogue.
     *
     * @param string           $directory The directory to look into
     * @param MessageCatalogue $catalogue The catalogue
     */
    public function loadMessages($directory, MessageCatalogue $catalogue)
    {
        $this->read($directory, $catalogue);
    }
}
