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
 * PhpDumper generates a php formated string representation of a message catalogue
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class PhpDumper implements DumperInterface
{
    /**
     * {@inheritDoc}
     */
    public function dump(MessageCatalogue $messages, $domain = 'messages')
    {
        $output = "<?php\n\nreturn ".var_export($messages->all($domain), true).";\n";

        return $output;
    }
}
