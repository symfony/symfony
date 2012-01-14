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

use Symfony\Component\Templating\TemplateNameParserInterface as BaseTemplateNameParserInterface;

/**
 * TemplateNameParserInterface converts template names to TemplateReferenceInterface
 * instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface TemplateNameParserInterface extends BaseTemplateNameParserInterface
{

    /**
     * Convert a filename to a template.
     *
     * @param string $file The filename
     *
     * @return TemplateReferenceInterface A template
     */
    public function parseFromFilename($file);

}
