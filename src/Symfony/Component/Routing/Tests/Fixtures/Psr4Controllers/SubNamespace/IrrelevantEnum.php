<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\SubNamespace;

/**
 * An irrelevant enum.
 *
 * This fixture is not referenced anywhere. Its presence makes sure, enums are silently ignored when loading routes
 * from a directory.
 */
enum IrrelevantEnum
{
    case Foo;
    case Bar;
}
