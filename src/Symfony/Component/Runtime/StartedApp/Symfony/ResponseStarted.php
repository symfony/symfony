<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\StartedApp\Symfony;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Runtime\StartedAppInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResponseStarted implements StartedAppInterface
{
    private $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function __invoke(): int
    {
        $this->response->send();

        return 0;
    }
}
