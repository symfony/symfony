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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

#[Group('functional')]
#[RequiresPhpExtension('intl')]
class ConsoleLocaleTest extends AbstractWebTestCase
{
    private string $previousLocale;

    protected function setUp(): void
    {
        $this->previousLocale = \Locale::getDefault();
        \Locale::setDefault('de');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->previousLocale);

        parent::tearDown();
    }

    public function testCommandSetsTheDefaultLocale()
    {
        static::bootKernel(['test_case' => 'ConsoleLocale']);

        $this->assertSame('de', \Locale::getDefault());

        $this->runListCommand();

        $this->assertSame('fr', \Locale::getDefault());
    }

    private function runListCommand(): void
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        (new ApplicationTester($application))->run(['command' => 'list']);
    }
}
