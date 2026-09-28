<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

/**
 * @phpstan-type Foo = int
 *
 * @phpstan-import-type Bar from DummyWithMutuallyImportedTypeAliasesB
 */
final class DummyWithMutuallyImportedTypeAliasesA
{
}
