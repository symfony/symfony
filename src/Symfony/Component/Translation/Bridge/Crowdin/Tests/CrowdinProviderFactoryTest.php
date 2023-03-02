<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin\Tests;

use Symfony\Component\Translation\Bridge\Crowdin\CrowdinProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class CrowdinProviderFactoryTest extends ProviderFactoryTestCase
{
    public static function supportsProvider(): iterable
    {
        yield [true, 'crowdin://PROJECT_ID:API_TOKEN@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_TOKEN@default'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'crowdin://api.crowdin.com',
            'crowdin://PROJECT_ID:API_TOKEN@default',
        ];

        yield [
            'crowdin://ORGANIZATION_DOMAIN.api.crowdin.com',
            'crowdin://PROJECT_ID:API_TOKEN@ORGANIZATION_DOMAIN.default',
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://API_TOKEN@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['crowdin://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new CrowdinProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), $this->getXliffFileDumper());
    }
}
