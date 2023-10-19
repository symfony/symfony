<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Templating\Tests\Fixtures\ProjectTemplateLoader;

/**
 * @group legacy
 */
class LoaderTest extends TestCase
{
    public function testGetSetLogger()
    {
        $loader = new ProjectTemplateLoader();
        $logger = $this->createMock(LoggerInterface::class);
        $loader->setLogger($logger);
        $this->assertSame($logger, $loader->getLogger(), '->setLogger() sets the logger instance');
    }
}
