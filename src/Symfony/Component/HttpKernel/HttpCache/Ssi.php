<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Ssi implements the SSI capabilities to Request and Response instances.
 */
class Ssi implements RendererInterface
{
    private $contentTypes;

    /**
     * Constructor.
     *
     * @param array $contentTypes An array of content-type that should be parsed for ESI information.
     *                           (default: text/html, text/xml, and application/xml)
     */
    public function __construct(array $contentTypes = array('text/html', 'text/xml', 'application/xml'))
    {
        $this->contentTypes = $contentTypes;
    }

    /**
     * Checks that at least one surrogate has ESI/1.0 capability.
     *
     * @param Request $request A Request instance
     *
     * @return Boolean true if one surrogate has ESI/1.0 capability, false otherwise
     */
    public function hasSurrogateCapability(Request $request)
    {
        return true;
        if (null === $value = $request->headers->get('Surrogate-Capability')) {
            return false;
        }

        return false !== strpos($value, 'SSI/1.0');
    }

    /**
     * Adds HTTP headers to specify that the Response needs to be parsed for ESI.
     *
     * This method only adds an ESI HTTP header if the Response has some ESI tags.
     *
     * @param Response $response A Response instance
     */
    public function addSurrogateControl(Response $response)
    {
        if (false !== strpos($response->getContent(), '<!--#include')) {
            $response->headers->set('Surrogate-Control', 'content="SSI/1.0"');
        }
    }

    /**
     * Renders an SSI tag.
     *
     * @param string  $uri          A URI
     * @param string  $alt          An alternate URI
     * @param Boolean $ignoreErrors Whether to ignore errors or not
     * @param string  $comment      A comment to add as an esi:include tag
     * @return string
     */
    public function renderIncludeTag($uri, $alt = null, $ignoreErrors = true, $comment = '')
    {
        $html = sprintf('<!--#include virtual="%s" -->',
            $uri
        );

        if (!empty($comment)) {
            return sprintf("<!-- %s -->\n%s", $comment, $html);
        }

        return $html;
    }
}
