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

/**
 * Ssi implements the SSI capabilities to Request and Response instances.
 *
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class Ssi extends AbstractSurrogate
{
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
    public function addSurrogateControl(Response $response)
    {
        if (false !== strpos($response->getContent(), '<!--#include')) {
            $response->headers->set('Surrogate-Control', 'content="SSI/1.0"');
        }
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
        $type = $response->headers->get('Content-Type');
        if (empty($type)) {
            $type = 'text/html';
        }

        $parts = explode(';', $type);
        if (!\in_array($parts[0], $this->contentTypes)) {
            return $response;
        }

        // we don't use a proper XML parser here as we can have SSI tags in a plain text response
        $content = $response->getContent();

        $chunks = preg_split('#<!--\#include\s+(.*?)\s*-->#', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $chunks[0] = str_replace($this->phpEscapeMap[0], $this->phpEscapeMap[1], $chunks[0]);

        $i = 1;
        while (isset($chunks[$i])) {
            $options = [];
            preg_match_all('/(virtual)="([^"]*?)"/', $chunks[$i], $matches, PREG_SET_ORDER);
            foreach ($matches as $set) {
                $options[$set[1]] = $set[2];
            }

            if (!isset($options['virtual'])) {
                throw new \RuntimeException('Unable to process an SSI tag without a "virtual" attribute.');
            }

            $chunks[$i] = sprintf('<?php echo $this->surrogate->handle($this, %s, \'\', false) ?>'."\n",
                var_export($options['virtual'], true)
            );
            ++$i;
            $chunks[$i] = str_replace($this->phpEscapeMap[0], $this->phpEscapeMap[1], $chunks[$i]);
            ++$i;
        }
        $content = implode('', $chunks);

        $response->setContent($content);
        $response->headers->set('X-Body-Eval', 'SSI');

        // remove SSI/1.0 from the Surrogate-Control header
        $this->removeFromControl($response);

        return $response;
    }
}
