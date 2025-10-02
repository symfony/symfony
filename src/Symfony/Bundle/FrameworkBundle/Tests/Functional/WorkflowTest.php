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

class WorkflowTest extends AbstractWebTestCase
{
    public function testWorkflowsDefinedWithAttributesAndConfigurationAreInjected()
    {
        $client = $this->createClient(['test_case' => 'Workflow', 'root_config' => 'config.yml']);
        $client->request('GET', '/workflow');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('open|resolved|closable|locked|Resolved;published', $client->getResponse()->getContent());
    }
}
