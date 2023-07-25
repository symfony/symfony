<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Runner\Symfony;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResponseRunner implements RunnerInterface
{
    public function __construct(
        private readonly Response $response,
    ) {
    }

    public function run(): int
    {
        $this->response->send();

        return 0;
    }
}
