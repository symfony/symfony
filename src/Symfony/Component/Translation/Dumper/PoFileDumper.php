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
use Symfony\Component\Translation\Gettext;

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
    public function format(MessageCatalogue $catalogue, $domain = 'messages')
    {
        $output = '';
        $messages = $catalogue->all();
        $header = Gettext::getHeader($messages['messages']);
        $newLine = false;
        if (!empty($header)) {
            $output .= Gettext::headerToString($header);
            $newLine = true;
        }
        Gettext::deleteHeader($messages['messages']);
        $messages = $messages[$domain];
        // Make plural form translations arrays
        $this->extractSingulars($messages);
        foreach ($messages as $source => $target) {
            if ($newLine) {
                $output .= "\n\n";
            } else {
                $newLine = true;
            }
            // Gettext PO files only understand non indexed rules or 'standard'
            if (!is_array($target)) {
                $output .= sprintf("msgid \"%s\"\n", $this->escape($source));
                $output .= sprintf("msgstr \"%s\"", $this->escape($target));
            } else {
                // ExtractSingular return 3 items so extract these.
                list( $singularKey, $plural_key, $targets) = $target;
                $output .= sprintf("msgid \"%s\"\n", $this->escape($source));
                $output .= sprintf("msgid_plural \"%s\"\n", $this->escape($plural_key));
                $targets = explode("|", $targets);
                foreach ($targets as $index => $target) {
                    if ($index>0) {
                        $output .= "\n";
                    }
                    $output .= sprintf('msgstr[%d] "%s"', $index, $this->escape($target));
                }
            }
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

    /**
     * Merges the singular and plurals back into 1 item.
     *
     * Gettext allows for a combination of messages being a singular and
     * a plural form for the source.
     *
     * msgid "One sheep"
     * msgid_plural "@count sheep"
     * msgstr[0] "un mouton"
     * msgstr[1] "@count moutons"
     *
     * By scanning $messages for "One sheep|@count sheep" we can recombine
     * these string for Dumping.
     *
     * @param array $messages All plural forms are merged into the first occurence of a singular.
     */
    private function extractSingulars(array &$messages)
    {
        $messageBundles = array();
        foreach ($messages as $key => $message) {
            if (strpos($key, '|') !== FALSE) {
                $messageBundles[] = $key;
            }
        }
        foreach ($messageBundles as $bundle) {
            list($singularKey, $pluralKey) = explode('|', $bundle, 2);
            if (isset($messages[$singularKey])&&isset($messages[$pluralKey])) {
                $messages[$singularKey] = array(
                    $singularKey,
                    $pluralKey,
                    $messages[$pluralKey],
                );
                unset($messages[$pluralKey]);
                unset($messages[$bundle]);
            }
        }
    }

}
