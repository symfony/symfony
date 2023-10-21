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

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', TemplateNameParser::class);

/**
 * TemplateNameParser is the default implementation of TemplateNameParserInterface.
 *
 * This implementation takes everything as the template name
 * and the extension for the engine.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
class TemplateNameParser implements TemplateNameParserInterface
{
    public function parse(string|TemplateReferenceInterface $name): TemplateReferenceInterface
    {
        if ($name instanceof TemplateReferenceInterface) {
            return $name;
        }

        $engine = null;
        if (false !== $pos = strrpos($name, '.')) {
            $engine = substr($name, $pos + 1);
        }

        return new TemplateReference($name, $engine);
    }
}
