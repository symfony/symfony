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
 * QtFileDumper generates ts files from a message catalogue.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class QtFileDumper extends FileDumper
{
    /**
     * {@inheritdoc}
     */
    public function format(MessageCatalogue $messages, $domain)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use the formatCatalogue() method instead.', E_USER_DEPRECATED);

        return $this->formatCatalogue($messages, $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function formatCatalogue(MessageCatalogue $messages, $domain, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $ts = $dom->appendChild($dom->createElement('TS'));
        $context = $ts->appendChild($dom->createElement('context'));
        $context->appendChild($dom->createElement('name', $domain));

        foreach ($messages->all($domain) as $source => $target) {
            $message = $context->appendChild($dom->createElement('message'));
            $message->appendChild($dom->createElement('source', $source));
            $message->appendChild($dom->createElement('translation', $target));
        }

        return $dom->saveXML();
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtension()
    {
        return 'ts';
    }
}
