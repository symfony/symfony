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
 * StreamingEngineInterface provides a method that knows how to stream a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface StreamingEngineInterface
{
    /**
     * Streams a template.
     *
     * The implementation should output the content directly to the client.
     *
     * @param mixed $name       A template name or a TemplateReferenceInterface instance
     * @param array $parameters An array of parameters to pass to the template
     *
     * @throws \RuntimeException if the template cannot be rendered
     */
    public function stream($name, array $parameters = array());
}
