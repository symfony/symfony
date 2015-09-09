<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Command;

use Symfony\Bundle\WebProfilerBundle\Command\ImportCommand;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * @group legacy
 */
class ImportCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $profiler->expects($this->once())->method('import')->will($this->returnValue(new Profile('TOKEN')));

        $helperSet = new HelperSet();
        $helper = $this->getMock('Symfony\Component\Console\Helper\FormatterHelper');
        $helper->expects($this->any())->method('formatSection');
        $helperSet->set($helper, 'formatter');

        $command = new ImportCommand($profiler);
        $command->setHelperSet($helperSet);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('filename' => __DIR__.'/../Fixtures/profile.data'));
        $this->assertRegExp('/Profile "TOKEN" has been successfully imported\./', $commandTester->getDisplay());
    }
}
