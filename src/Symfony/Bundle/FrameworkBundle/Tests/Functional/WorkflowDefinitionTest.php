<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

final class WorkflowDefinitionTest extends AbstractWebTestCase
{
    public function testAttributeAndConfiguredWorkflowsAreUsedByAController()
    {
        $client = self::createClient(['test_case' => 'WorkflowDefinition']);
        $client->request('GET', '/workflow-definition');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame('order:draft|yes|reviewed;article:draft|yes|published', $client->getResponse()->getContent());
    }
}
