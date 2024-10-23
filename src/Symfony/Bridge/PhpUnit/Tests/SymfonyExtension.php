<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\symfonyextension\src\ClassExtendingFinalClass;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\symfonyextension\src\FinalClass;

class SymfonyExtension extends TestCase
{
    public function testExtensionOfFinalClass()
    {
        $this->expectUserDeprecationMessage(\sprintf('The "%s" class is considered final. It may change without further notice as of its next major version. You should not extend it from "%s".', FinalClass::class, ClassExtendingFinalClass::class));

        new ClassExtendingFinalClass();
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('time-sensitive')]
    public function testTimeMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\time', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('time-sensitive')]
    public function testMicrotimeMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\microtime', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('time-sensitive')]
    public function testSleepMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\sleep', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('time-sensitive')]
    public function testUsleepMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\usleep', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('time-sensitive')]
    public function testDateMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\date', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('time-sensitive')]
    public function testGmdateMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\gmdate', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('time-sensitive')]
    public function testHrtimeMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\hrtime', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testCheckdnsrrMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\checkdnsrr', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testDnsCheckRecordMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\dns_check_record', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testGetmxrrMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\getmxrr', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testDnsGetMxMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\dns_get_mx', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testGethostbyaddrMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\gethostbyaddr', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testGethostbynameMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\gethostbyname', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testGethostbynamelMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\gethostbynamel', $namespace)));
    }

    #[DataProvider('mockedNamespaces')]
    #[Group('dns-sensitive')]
    public function testDnsGetRecordMockIsRegistered(string $namespace)
    {
        $this->assertTrue(\function_exists(\sprintf('%s\dns_get_record', $namespace)));
    }

    public static function mockedNamespaces(): iterable
    {
        yield 'test class namespace' => [__NAMESPACE__];
        yield 'namespace derived from test namespace' => ['Symfony\Bridge\PhpUnit'];
        yield 'explicitly configured namespace' => ['App'];
    }
}
