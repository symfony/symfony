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
 *
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class Ssi implements SurrogateInterface
{
    private $contentTypes;

    /**
     * Constructor.
     *
     * @param array $contentTypes An array of content-type that should be parsed for SSI information.
     *                            (default: text/html, text/xml, application/xhtml+xml, and application/xml)
     */
    public function __construct(array $contentTypes = array('text/html', 'text/xml', 'application/xhtml+xml', 'application/xml'))
    {
        $this->contentTypes = $contentTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ssi';
    }

    /**
     * {@inheritdoc}
     */
    public function createCacheStrategy()
    {
        return new ResponseCacheStrategy();
    }

    /**
     * {@inheritdoc}
     */
    public function hasSurrogateCapability(Request $request)
    {
        if (null === $value = $request->headers->get('Surrogate-Capability')) {
            return false;
        }

        return false !== strpos($value, 'SSI/1.0');
    }

    /**
     * {@inheritdoc}
     */
    public function addSurrogateCapability(Request $request)
    {
        $current = $request->headers->get('Surrogate-Capability');
        $new = 'symfony2="SSI/1.0"';

        $request->headers->set('Surrogate-Capability', $current ? $current.', '.$new : $new);
    }

    /**
     * {@inheritdoc}
     */
    public function addSurrogateControl(Response $response)
    {
        if (false !== strpos($response->getContent(), '<!--#include')) {
            $response->headers->set('Surrogate-Control', 'content="SSI/1.0"');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function needsParsing(Response $response)
    {
        if (!$control = $response->headers->get('Surrogate-Control')) {
            return false;
        }

        return (bool) preg_match('#content="[^"]*SSI/1.0[^"]*"#', $control);
    }

    /**
     * {@inheritdoc}
     */
    public function renderIncludeTag($uri, $alt = null, $ignoreErrors = true, $comment = '')
    {
        return sprintf('<!--#include virtual="%s" -->', $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, Response $response)
    {
        $this->request = $request;
        $type = $response->headers->get('Content-Type');
        if (empty($type)) {
            $type = 'text/html';
        }

        $parts = explode(';', $type);
        if (!in_array($parts[0], $this->contentTypes)) {
            return $response;
        }

        // we don't use a proper XML parser here as we can have SSI tags in a plain text response
        $content = $response->getContent();
        $content = str_replace(array('<?', '<%'), array('<?php echo "<?"; ?>', '<?php echo "<%"; ?>'), $content);
        $content = preg_replace_callback('#<!--\#include\s+(.*?)\s*-->#', array($this, 'handleIncludeTag'), $content);

        $response->setContent($content);
        $response->headers->set('X-Body-Eval', 'SSI');

        // remove SSI/1.0 from the Surrogate-Control header
        if ($response->headers->has('Surrogate-Control')) {
            $value = $response->headers->get('Surrogate-Control');
            if ('content="SSI/1.0"' == $value) {
                $response->headers->remove('Surrogate-Control');
            } elseif (preg_match('#,\s*content="SSI/1.0"#', $value)) {
                $response->headers->set('Surrogate-Control', preg_replace('#,\s*content="SSI/1.0"#', '', $value));
            } elseif (preg_match('#content="SSI/1.0",\s*#', $value)) {
                $response->headers->set('Surrogate-Control', preg_replace('#content="SSI/1.0",\s*#', '', $value));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(HttpCache $cache, $uri, $alt, $ignoreErrors)
    {
        $subRequest = Request::create($uri, 'get', array(), $cache->getRequest()->cookies->all(), array(), $cache->getRequest()->server->all());

        try {
            $response = $cache->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);

            if (!$response->isSuccessful()) {
                throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $subRequest->getUri(), $response->getStatusCode()));
            }

            return $response->getContent();
        } catch (\Exception $e) {
            if ($alt) {
                return $this->handle($cache, $alt, '', $ignoreErrors);
            }

            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }

    /**
     * Handles an SSI include tag (called internally).
     *
     * @param array $attributes An array containing the attributes.
     *
     * @return string The response content for the include.
     *
     * @throws \RuntimeException
     */
    private function handleIncludeTag($attributes)
    {
        $options = array();
        preg_match_all('/(virtual)="([^"]*?)"/', $attributes[1], $matches, PREG_SET_ORDER);
        foreach ($matches as $set) {
            $options[$set[1]] = $set[2];
        }

        if (!isset($options['virtual'])) {
            throw new \RuntimeException('Unable to process an SSI tag without a "virtual" attribute.');
        }

        return sprintf('<?php echo $this->surrogate->handle($this, %s, \'\', false) ?>'."\n",
            var_export($options['virtual'], true)
        );
    }
}
