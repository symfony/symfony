<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Command\Command as InputFile;

/**
 * Here the "InputFile" short name resolves to a different class, so detection must read the
 * `use` statements rather than match the short name blindly.
 */
class InputFileTypeAliasFixture
{
    /**
     * @param InputFile[] $files
     */
    public function sameShortName(array $files): void
    {
    }

    /**
     * @param \Symfony\Component\Console\Input\File\InputFile[] $files
     */
    public function fullyQualified(array $files): void
    {
    }
}
