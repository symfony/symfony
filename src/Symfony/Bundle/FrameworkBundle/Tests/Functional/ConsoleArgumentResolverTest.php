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
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

#[Group('functional')]
class ConsoleArgumentResolverTest extends AbstractWebTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(ValueResolverInterface::class)) {
            self::markTestSkipped('Console ArgumentResolver not available.');
        }

        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'ConsoleArgumentResolver', 'root_config' => 'config.yml']);
    }

    public function testCustomArgumentResolver()
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run([
            'command' => 'app:custom-type',
            'name' => 'test-value',
            'count' => '10',
            'status' => 'inactive',
            '--format' => 'xml',
        ]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('CustomType value: resolved:test-value', $output);
        $this->assertStringContainsString('Name: test-value', $output);
        $this->assertStringContainsString('Count: 10', $output);
        $this->assertStringContainsString('Status: inactive', $output);
        $this->assertStringContainsString('Date: '.date('Y-m-d'), $output);
        $this->assertStringContainsString('Service: Service injected!', $output);
        $this->assertStringContainsString('Format: xml', $output);
        $this->assertStringContainsString('CustomOption: option:xml', $output);
    }

    public function testAdvancedFeatures()
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run([
            'command' => 'app:advanced',
            'name' => 'test-advanced',
        ]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('Autowired: Service injected!', $output);
        $this->assertStringContainsString('Environment:', $output);
        $this->assertStringContainsString('Targeted: Service injected!', $output);
        $this->assertStringContainsString('Regular: Service injected!', $output);
        $this->assertStringContainsString('Name: test-advanced', $output);
    }

    public function testValueResolverAutoconfiguration()
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);

        // Test auto-tagged value resolver (implements ValueResolverInterface)
        $tester->run(['command' => 'app:resolver-test', 'scenario' => 'auto-tagged']);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Auto-tagged: auto-tagged-value', $tester->getDisplay());

        // Test targeted value resolver (#[AsTargetedValueResolver])
        $tester->run(['command' => 'app:resolver-test', 'scenario' => 'targeted']);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Targeted: targeted-value', $tester->getDisplay());
    }
}
