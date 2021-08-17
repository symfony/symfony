<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

/**
 * TemplateNameParserInterface converts template names to TemplateReferenceInterface
 * instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface TemplateNameParserInterface
{
    /**
     * Convert a template name to a TemplateReferenceInterface instance.
     */
    public function parse(string|TemplateReferenceInterface $name): TemplateReferenceInterface;
}
