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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractWebTestCase extends BaseWebTestCase
{
    public static function assertRedirect($response, $location)
    {
        self::assertTrue($response->isRedirect(), 'Response is not a redirect, got status code: '.substr($response, 0, 2000));
        self::assertEquals('http://localhost'.$location, $response->headers->get('Location'));
    }

    public static function setUpBeforeClass(): void
    {
        static::deleteTmpDir();
    }

    public static function tearDownAfterClass(): void
    {
        static::deleteTmpDir();
    }

    public static function provideSecuritySystems()
    {
        yield [['enable_authenticator_manager' => true]];
        yield [['enable_authenticator_manager' => false]];
    }

    protected static function deleteTmpDir()
    {
        if (!file_exists($dir = sys_get_temp_dir().'/'.static::getVarDir())) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }

    protected static function getKernelClass(): string
    {
        require_once __DIR__.'/app/AppKernel.php';

        return 'Symfony\Bundle\SecurityBundle\Tests\Functional\app\AppKernel';
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        $class = self::getKernelClass();

        if (!isset($options['test_case'])) {
            throw new \InvalidArgumentException('The option "test_case" must be set.');
        }

        return new $class(
            static::getVarDir(),
            $options['test_case'],
            $options['root_config'] ?? 'config.yml',
            $options['environment'] ?? strtolower(static::getVarDir().$options['test_case']),
            $options['debug'] ?? false,
            $options['enable_authenticator_manager'] ?? false
        );
    }

    protected static function getVarDir()
    {
        return 'SB'.substr(strrchr(static::class, '\\'), 1);
    }
}
