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

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', StreamingEngineInterface::class);

/**
 * StreamingEngineInterface provides a method that knows how to stream a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
interface StreamingEngineInterface
{
    /**
     * Streams a template.
     *
     * The implementation should output the content directly to the client.
     *
     * @return void
     *
     * @throws \RuntimeException if the template cannot be rendered
     * @throws \LogicException   if the template cannot be streamed
     */
    public function stream(string|TemplateReferenceInterface $name, array $parameters = []);
}
