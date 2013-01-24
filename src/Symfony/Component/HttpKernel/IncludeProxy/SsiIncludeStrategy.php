<?php
namespace Symfony\Component\HttpKernel\IncludeProxy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class SsiIncludeStrategy implements IncludeStrategyInterface
{
    public function getName ()
    {
        return 'SSI/1.0';
    }

    public function handle (HttpKernelInterface $kernel, Request $request, Response $response)
    {
        $extractor = function ($attributes) {
            $options = array();
            preg_match_all('/(virtual|fmt)="([^"]*?)"/', $attributes[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $set) {
                $options[$set[1]] = $set[2];
            }

            return array (
                isset($options['virtual']) ? $options['virtual'] : null,
                null,
                isset($options['fmt']) && $options['fmt'] == '?'
            );

        };
        return preg_replace_callback('#<!--\#include\s+(.*?)\s*-->#', new IncludeHandler($kernel, $request, $response, $extractor), $response->getContent());
    }
}
