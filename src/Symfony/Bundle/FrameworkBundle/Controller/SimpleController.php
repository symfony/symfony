<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Generates a simple HTTP Response.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class SimpleController
{
    public function __invoke(string $content = '', int $status = 200, array $headers = [], int $maxAge = null, int $sharedAge = null, bool $private = null): Response
    {
        $response = new Response($content, $status, $headers);

        if ($maxAge) {
            $response->setMaxAge($maxAge);
        }

        if ($sharedAge) {
            $response->setSharedMaxAge($sharedAge);
        }

        if ($private) {
            $response->setPrivate();
        } elseif (false === $private || (null === $private && ($maxAge || $sharedAge))) {
            $response->setPublic();
        }

        return $response;
    }
}
