<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\CacheWarmer;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\CacheWarmer\ProxyCacheWarmer;

class ProxyCacheWarmerTest extends TestCase
{
    public function testWarmUpFiltersNonPhpFiles()
    {
        $proxyDir = sys_get_temp_dir().'/doctrine_proxy_test_'.uniqid();
        mkdir($proxyDir, 0o777, true);

        try {
            // simulate the atomic-write temp file left by ProxyFactory::generateProxyClasses()
            file_put_contents($proxyDir.'/__CG__Foo.php', '<?php // proxy');
            file_put_contents($proxyDir.'/__CG__Foo.php.2eb37f8174836c6595d337b3', '<?php // temp');

            $proxyFactory = $this->createMock(ProxyFactory::class);
            $proxyFactory->expects($this->once())
                ->method('generateProxyClasses')
                ->willReturn(1);

            $configuration = $this->createStub(Configuration::class);
            $configuration->method('getProxyDir')->willReturn($proxyDir);
            $configuration->method('getAutoGenerateProxyClasses')->willReturn(0);

            $metadataFactory = $this->createStub(ClassMetadataFactory::class);
            $metadataFactory->method('getAllMetadata')->willReturn([]);

            $em = $this->createStub(EntityManagerInterface::class);
            $em->method('getConfiguration')->willReturn($configuration);
            $em->method('getProxyFactory')->willReturn($proxyFactory);
            $em->method('getMetadataFactory')->willReturn($metadataFactory);

            $registry = $this->createStub(ManagerRegistry::class);
            $registry->method('getManagers')->willReturn([$em]);

            $result = (new ProxyCacheWarmer($registry))->warmUp('/cache');

            $this->assertContains($proxyDir.'/__CG__Foo.php', $result);
            $this->assertNotContains($proxyDir.'/__CG__Foo.php.2eb37f8174836c6595d337b3', $result, 'warmUp() must not return atomic-write temp files that do not end in .php');
        } finally {
            array_map('unlink', glob($proxyDir.'/*'));
            rmdir($proxyDir);
        }
    }
}
