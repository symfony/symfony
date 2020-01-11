<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

/**
 * @internal
 */
trait SetUpTearDownTraitForV8
{
    public static function setUpBeforeClass(): void
    {
        static::doSetUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        static::doTearDownAfterClass();
    }

    protected function setUp(): void
    {
        static::doSetUp();
    }

    protected function tearDown(): void
    {
        static::doTearDown();
    }

    private static function doSetUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    private static function doTearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    private function doSetUp(): void
    {
        parent::setUp();
    }

    private function doTearDown(): void
    {
        parent::tearDown();
    }
}
