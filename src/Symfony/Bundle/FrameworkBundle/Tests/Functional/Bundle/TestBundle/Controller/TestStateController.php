<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\TestStateService;
use Symfony\Component\HttpFoundation\Response;

class TestStateController
{
    public function __construct(
        private TestStateService $service,
    ) {
    }

    public function __invoke(string $record): Response
    {
        $this->service->record($record);

        return new Response('');
    }
}
