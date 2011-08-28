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
use Symfony\Component\Yaml\Yaml;

/**
 * YamlDumper generates a yaml formated string representation of a message catalogue
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class YamlDumper implements DumperInterface
{
    /**
     * {@inheritDoc}
     */
    public function dump(MessageCatalogue $messages, $domain = 'messages')
    {
         return Yaml::dump($messages->all($domain));
    }
}
