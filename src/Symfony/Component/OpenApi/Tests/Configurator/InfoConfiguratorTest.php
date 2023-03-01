<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Tests\Configurator;

use Symfony\Component\OpenApi\Configurator\InfoConfigurator;
use Symfony\Component\OpenApi\Model\Info;

class InfoConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testBuildEmpty(): void
    {
        $configurator = new InfoConfigurator();

        $info = $configurator->build('', '');
        $this->assertSame('', $info->getTitle());
        $this->assertSame('', $info->getVersion());
        $this->assertNull($info->getSummary());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getTermsOfService());
        $this->assertNull($info->getContact());
        $this->assertNull($info->getLicense());
        $this->assertSame([], $info->getSpecificationExtensions());
    }

    public function testBuildPartialContactLicense(): void
    {
        $configurator = (new InfoConfigurator())
            ->title('Symfony OpenApi')
            ->version('1.2.3')
            ->contact(url: 'https://selency.fr')
            ->license('MIT License')
        ;

        $info = $configurator->build();
        $this->assertSame('Symfony OpenApi', $info->getTitle());
        $this->assertSame('1.2.3', $info->getVersion());
        $this->assertNotNull($info->getContact());
        $this->assertNull($info->getContact()->getName());
        $this->assertSame('https://selency.fr', $info->getContact()->getUrl());
        $this->assertNull($info->getContact()->getEmail());
        $this->assertNotNull($info->getLicense());
        $this->assertSame('MIT License', $info->getLicense()->getName());
        $this->assertNull($info->getLicense()->getIdentifier());
        $this->assertNull($info->getLicense()->getUrl());
        $this->assertSame([], $info->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        $configurator = (new InfoConfigurator())
            ->title('Symfony OpenApi')
            ->version('1.2.3')
            ->contact(name: 'Selency', url: 'https://selency.fr', email: 'tech@selency.com', specificationExtensions: ['x-ext1' => 'value'])
            ->license(name: 'MIT License', identifier: 'MIT', url: 'https://example.com', specificationExtensions: ['x-ext2' => 'value'])
            ->specificationExtension('x-ext3', 'value')
        ;

        $info = $configurator->build();
        $this->assertInstanceOf(Info::class, $info);
        $this->assertSame('Symfony OpenApi', $info->getTitle());
        $this->assertSame('1.2.3', $info->getVersion());
        $this->assertSame(['x-ext3' => 'value'], $info->getSpecificationExtensions());
        $this->assertNotNull($info->getContact());
        $this->assertSame('Selency', $info->getContact()->getName());
        $this->assertSame('https://selency.fr', $info->getContact()->getUrl());
        $this->assertSame('tech@selency.com', $info->getContact()->getEmail());
        $this->assertSame(['x-ext1' => 'value'], $info->getContact()->getSpecificationExtensions());
        $this->assertNotNull($info->getLicense());
        $this->assertSame('MIT License', $info->getLicense()->getName());
        $this->assertSame('MIT', $info->getLicense()->getIdentifier());
        $this->assertSame('https://example.com', $info->getLicense()->getUrl());
        $this->assertSame(['x-ext2' => 'value'], $info->getLicense()->getSpecificationExtensions());
    }
}
