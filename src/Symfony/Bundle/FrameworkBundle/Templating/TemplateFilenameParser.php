<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\Templating\TemplateNameParserInterface;

/**
 * TemplateFilenameParser converts template filenames to
 * TemplateReferenceInterface instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateFilenameParser implements TemplateNameParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function parse($file)
    {
        $parts = explode('/', str_replace('\\', '/', $file));

        $elements = explode('.', array_pop($parts));
        if (3 > count($elements)) {
            return false;
        }
        $engine = array_pop($elements);
        $format = array_pop($elements);

        return new TemplateReference('', implode('/', $parts), implode('.', $elements), $format, $engine);
    }
}
