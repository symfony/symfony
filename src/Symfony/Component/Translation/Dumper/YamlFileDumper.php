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
 * YamlFileDumper generates yaml files from a message catalogue.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class YamlFileDumper extends FileDumper
{
    /**
     * @var integer Nesting depth. 0 means one line by message, 1 will
     * indent at most one time, and so on.
     */
    public $nestLevel = 0;

    /**
     * {@inheritDoc}
     */
    public function dump(MessageCatalogue $messages, $options = array())
    {
        $this->nestLevel = array_key_exists('nest-level', $options) ? $options['nest-level'] : 0;
        print_r($options);

        parent::dump($messages, $options);
    }
    /**
     * {@inheritDoc}
     */
    protected function format(MessageCatalogue $messages, $domain)
    {
        $m = $messages->all($domain);

        if ($this->nestLevel > 0) {
            // build a message tree from the message list, with a max depth
            // of $this->nestLevel
            $tree = array();
            foreach ($m as $key => $message) {

                // dots are ignored at the beginning and at the end of a key
                $key = trim($key, "\t .");

                if (strlen($key) > 0) {
                    $codes = explode('.', $key, $this->nestLevel+1);
                    $node = &$tree;

                    foreach ($codes as $code) {
                        if (strlen($code) > 0) {
                            if (!isset($node)) {
                                $node = array();
                            }
                            $node = &$node[$code];
                        }
                    }
                    $node = $message;
                }
            }

            return Yaml::dump($tree, $this->nestLevel+1); // second parameter at 1 outputs normal line-by-line catalogue
        } else {
            return Yaml::dump($m, 1);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtension()
    {
        return 'yml';
    }
}
