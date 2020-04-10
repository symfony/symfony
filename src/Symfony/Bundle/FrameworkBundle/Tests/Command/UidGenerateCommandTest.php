<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Command\UidGenerateCommand;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Tester\CommandTester;

class UidGenerateCommandTest extends TestCase
{
    public function testGenerateUid()
    {
        $tester = $this->getTester();

        $tester->execute(['type' => 'uuid-1']);
        $this->assertRegExp('/[a-f\d]{8}\-[a-f\d]{4}\-1[a-f\d]{3}\-[a-f\d]{4}\-[a-f\d]{8}/i', $tester->getDisplay());

        $tester->execute(['type' => 'uuid-3', 'namespace' => 'a1dc606e-741b-11ea-aa36-99e245e7882b', 'name' => 'foo']);
        $this->assertStringContainsString('ad4ab486-b67f-3d46-881f-21f03d27a68b', $tester->getDisplay());

        $tester->execute(['type' => 'uuid-4']);
        $this->assertRegExp('/[a-f\d]{8}\-[a-f\d]{4}\-4[a-f\d]{3}\-[a-f\d]{4}\-[a-f\d]{8}/i', $tester->getDisplay());

        $tester->execute(['type' => 'uuid-5', 'namespace' => 'a1dc606e-741b-11ea-aa36-99e245e7882b', 'name' => 'foo']);
        $this->assertStringContainsString('d87f160a-3cc6-520e-845f-112865bed05c', $tester->getDisplay());

        $tester->execute(['type' => 'uuid-6']);
        $this->assertRegExp('/[a-f\d]{8}\-[a-f\d]{4}\-6[a-f\d]{3}\-[a-f\d]{4}\-[a-f\d]{8}/i', $tester->getDisplay());

        $tester->execute(['type' => 'ulid']);
        $this->assertRegExp('/[0-9A-HJKMNP-TV-Z]{26}/i', $tester->getDisplay());

        $tester->execute(['type' => 'uuid-1', '--base32' => true]);
        $this->assertRegExp('/[0-9A-HJKMNP-TV-Z]{26}/i', $tester->getDisplay());

        $tester->execute(['type' => 'uuid-1', '--base58' => true]);
        $this->assertRegExp('/[1-9A-HJ-NP-Za-km-z]{22}/i', $tester->getDisplay());
    }

    public function getTester(): CommandTester
    {
        $application = new BaseApplication();
        $application->add(new UidGenerateCommand());
        $command = $application->find('uid:generate');

        return new CommandTester($command);
    }
}
