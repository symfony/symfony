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

/**
 * XliffFileDumper generates xliff files with 'resname' attribute support from a message catalogue.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class XliffFileWithResnameDumper extends XliffFileDumper
{
    /**
     * {@inheritdoc}
     */
    protected function buildTranslation(\DOMElement $node, $id, $source, $target)
    {
        parent::buildTranslation($node, $id, $source, $target);
        $node->setAttribute('resname', $source);
    }
}
