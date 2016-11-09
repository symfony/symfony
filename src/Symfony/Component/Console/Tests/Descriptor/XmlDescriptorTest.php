<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Tests\Fixtures\TestCommand;

class XmlDescriptorTest extends AbstractDescriptorTest
{
    public function testGetApplicationDocument()
    {
        $xmlDescriptor = $this->getDescriptor();
        $application = new Application();
        $application->addCommands([new TestCommand()]);
        $domDocument = $xmlDescriptor->getApplicationDocument($application, 'namespace');
        $this->assertStringStartsWith('namespace', $domDocument->textContent);
    }

    protected function getDescriptor()
    {
        return new XmlDescriptor();
    }

    protected function getFormat()
    {
        return 'xml';
    }
}
