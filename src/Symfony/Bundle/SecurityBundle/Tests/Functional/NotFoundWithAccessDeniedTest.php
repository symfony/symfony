<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class NotFoundWithAccessDeniedTest extends AbstractWebTestCase
{
    public function testNotFoundWithAccessDenied()
    {
        $client = $this->createClient(['test_case' => 'NotFoundWithAccessDenied', 'root_config' => 'config.yml']);

        $client->request('GET', '/');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }
}

class NotFoundAccessDeniedController
{
    public function __invoke()
    {
        throw new NotFoundHttpException('Not found', new AccessDeniedException());
    }
}
