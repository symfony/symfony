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

@trigger_error('The '.StreamingEngineInterface::class.' interface is deprecated since version 3.3 and will be removed in 4.0. Use Twig instead.', E_USER_DEPRECATED);

/**
 * StreamingEngineInterface provides a method that knows how to stream a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated The StreamingEngineInterface interface will be removed in Symfony 4.0. You should use Twig instead.
 */
interface StreamingEngineInterface
{
    /**
     * Streams a template.
     *
     * The implementation should output the content directly to the client.
     *
     * @param string|TemplateReferenceInterface $name       A template name or a TemplateReferenceInterface instance
     * @param array                             $parameters An array of parameters to pass to the template
     *
     * @throws \RuntimeException if the template cannot be rendered
     * @throws \LogicException   if the template cannot be streamed
     */
    public function stream($name, array $parameters = array());
}
