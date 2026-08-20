<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\PoEditor\Tests;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\PoEditor\PoEditorProviderFactory;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\AbstractProviderFactoryTestCase;
use Symfony\Component\Translation\Test\IncompleteDsnTestTrait;

class PoEditorProviderFactoryTest extends AbstractProviderFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public static function supportsProvider(): iterable
    {
        yield [true, 'poeditor://PROJECT_ID:API_KEY@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'poeditor://api.poeditor.com',
            'poeditor://PROJECT_ID:API_KEY@default',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['poeditor://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new PoEditorProviderFactory(new MockHttpClient(), new NullLogger(), 'en', new ArrayLoader());
    }
}
