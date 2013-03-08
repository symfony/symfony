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

use Symfony\Component\DependencyInjection\SimpleXMLElement;

/**
 * XliffFileLoader2 loads translations from XLIFF files with 'resname' attribute support.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @api
 */
class XliffFileLoader2 extends XliffFileLoader
{
    /**
     * {@inheritdoc}
     */
    protected function validateTranslation(\SimpleXMLElement $translation)
    {
        $attributes = $translation->attributes();

        return (isset($attributes['resname']) || isset($translation->source)) && isset($translation->target);
    }

    /**
     * {@inheritdoc}
     */
    protected function parseTranslation(\SimpleXMLElement $translation)
    {
        $attributes = $translation->attributes();
        $source = isset($attributes['resname']) && $attributes['resname'] ? $attributes['resname'] : $translation->source;

        return array((string) $source, (string) $translation->target);
    }
}
