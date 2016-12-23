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

@trigger_error('The '.TemplateNameParserInterface::class.' interface is deprecated since version 3.3 and will be removed in 4.0. Use Twig instead.', E_USER_DEPRECATED);

/**
 * TemplateNameParserInterface converts template names to TemplateReferenceInterface
 * instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated The TemplateNameParserInterface interface will be removed in Symfony 4.0. You should use Twig instead.
 */
interface TemplateNameParserInterface
{
    /**
     * Convert a template name to a TemplateReferenceInterface instance.
     *
     * @param string|TemplateReferenceInterface $name A template name or a TemplateReferenceInterface instance
     *
     * @return TemplateReferenceInterface A template
     */
    public function parse($name);
}
