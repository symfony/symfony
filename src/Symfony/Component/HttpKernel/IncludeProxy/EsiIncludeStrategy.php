<?php
namespace Symfony\Component\HttpKernel\IncludeProxy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class EsiIncludeStrategy implements IncludeStrategyInterface
{
    public function getName ()
    {
        return 'ESI/1.0';
    }

    public function handle (HttpKernelInterface $kernel, Request $request, Response $response)
    {
        $extractor = function ($attributes) {
            $options = array();
            preg_match_all('/(src|onerror|alt)="([^"]*?)"/', $attributes[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $set) {
                $options[$set[1]] = $set[2];
            }

            return array(
                isset($options['src']) ? $options['virtual'] : null,
                isset($options['alt']) ? $options['alt'] : null,
                isset($options['onerror']) && $options['onerror'] == 'continue'
            );

        };
        return preg_replace_callback('#<esi\:include\s+(.*?)\s*(?:/|</esi\:include)>#', new IncludeHandler($kernel, $request, $response, $extractor), $response->getContent());
    }
}
