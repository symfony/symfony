<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Exception\FileLoaderLoadException;

class FileLoaderLoadExceptionTest extends TestCase
{
    public function testMessageCannotLoadResource(): void
    {
        $exception = new FileLoaderLoadException('resource', null);
        $this->assertEquals('Cannot load resource "resource".', $exception->getMessage());
    }

    public function testMessageCannotLoadResourceWithType(): void
    {
        $exception = new FileLoaderLoadException('resource', null, null, null, 'foobar');
        $this->assertEquals('Cannot load resource "resource". Make sure there is a loader supporting the "foobar" type.', $exception->getMessage());
    }

    public function testMessageCannotLoadResourceWithAnnotationType(): void
    {
        $exception = new FileLoaderLoadException('resource', null, null, null, 'annotation');
        $this->assertEquals('Cannot load resource "resource". Make sure annotations are installed and enabled.', $exception->getMessage());
    }

    public function testMessageCannotImportResourceFromSource(): void
    {
        $exception = new FileLoaderLoadException('resource', 'sourceResource');
        $this->assertEquals('Cannot import resource "resource" from "sourceResource".', $exception->getMessage());
    }

    public function testMessageCannotImportBundleResource(): void
    {
        $exception = new FileLoaderLoadException('@resource', 'sourceResource');
        $this->assertEquals(
            'Cannot import resource "@resource" from "sourceResource". '.
            'Make sure the "resource" bundle is correctly registered and loaded in the application kernel class. '.
            'If the bundle is registered, make sure the bundle path "@resource" is not empty.',
            $exception->getMessage()
        );
    }

    public function testMessageHasPreviousErrorWithDotAndUnableToLoad(): void
    {
        $exception = new FileLoaderLoadException(
            'resource',
            null,
            null,
            new \Exception('There was a previous error with an ending dot.')
        );
        $this->assertEquals(
            'There was a previous error with an ending dot in resource (which is loaded in resource "resource").',
            $exception->getMessage()
        );
    }

    public function testMessageHasPreviousErrorWithoutDotAndUnableToLoad(): void
    {
        $exception = new FileLoaderLoadException(
            'resource',
            null,
            null,
            new \Exception('There was a previous error with no ending dot')
        );
        $this->assertEquals(
            'There was a previous error with no ending dot in resource (which is loaded in resource "resource").',
            $exception->getMessage()
        );
    }

    public function testMessageHasPreviousErrorAndUnableToLoadBundle(): void
    {
        $exception = new FileLoaderLoadException(
            '@resource',
            null,
            null,
            new \Exception('There was a previous error with an ending dot.')
        );
        $this->assertEquals(
            'There was a previous error with an ending dot in @resource '.
            '(which is loaded in resource "@resource"). '.
            'Make sure the "resource" bundle is correctly registered and loaded in the application kernel class. '.
            'If the bundle is registered, make sure the bundle path "@resource" is not empty.',
            $exception->getMessage()
        );
    }
}
