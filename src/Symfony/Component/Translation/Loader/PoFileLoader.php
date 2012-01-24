<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Config\Resource\FileResource;

/**
 * @copyright Copyright (c) 2010, Union of RAD http://union-of-rad.org (http://lithify.me/)
 */
class PoFileLoader extends ArrayLoader implements LoaderInterface
{
    public function load($resource, $locale, $domain = 'messages')
    {
        $messages = $this->parse($resource);

        // empty file
        if (null === $messages) {
            $messages = array();
        }

        // not an array
        if (!is_array($messages)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" must contain a valid po file.', $resource));
        }

        $catalogue = parent::load($messages, $locale, $domain);
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }

    /**
     * Parses portable object (PO) format.
     *
     * This parser sacrifices some features of the reference implementation the
     * differences to that implementation are as follows.
     * - No support for comments spanning multiple lines.
     * - Translator and extracted comments are treated as being the same type.
     * - Message IDs are allowed to have other encodings as just US-ASCII.
     *
     * Items with an empty id are ignored.
     *
     * @param resource $resource
     *
     * @return array
     */
    private function parse($resource)
    {
        $stream = fopen($resource, 'r');

        $defaults = array(
            'ids' => array(),
            'translated' => null,
        );

        $messages = array();
        $item = $defaults;

        while ($line = fgets($stream)) {
            $line = trim($line);

            if ($line === '') {
                if (is_array($item['translated'])) {
                    $messages[$item['ids']['singular']] = stripslashes($item['translated'][0]);
                    if (isset($item['ids']['plural'])) {
                        $plurals = array();
                        foreach ($item['translated'] as $plural => $translated) {
                            $plurals[] = sprintf('{%d} %s', $plural, $translated);
                        }
                        $messages[$item['ids']['plural']] = stripcslashes(implode('|', $plurals));
                    }
                } elseif(!empty($item['ids']['singular'])) {
                    $messages[$item['ids']['singular']] = stripslashes($item['translated']);
                }
                $item = $defaults;
            } elseif (substr($line, 0, 7) === 'msgid "') {
                $item['ids']['singular'] = substr($line, 7, -1);
            } elseif (substr($line, 0, 8) === 'msgstr "') {
                $item['translated'] = substr($line, 8, -1);
            } elseif ($line[0] === '"') {
                $continues = isset($item['translated']) ? 'translated' : 'ids';

                if (is_array($item[$continues])) {
                    end($item[$continues]);
                    $item[$continues][key($item[$continues])] .= substr($line, 1, -1);
                } else {
                    $item[$continues] .= substr($line, 1, -1);
                }
            } elseif (substr($line, 0, 14) === 'msgid_plural "') {
                $item['ids']['plural'] = substr($line, 14, -1);
            } elseif (substr($line, 0, 7) === 'msgstr[') {
                $size = strpos($line, ']');
                $item['translated'][(integer) substr($line, 7, 1)] = substr($line, $size + 3, -1);
            }

        }
        fclose($stream);

        return array_filter($messages);
    }
}
