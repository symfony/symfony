<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\EntryTypeProviderInterface;

class NumericEntryTypeProvider implements EntryTypeProviderInterface
{
    public function forModelData(mixed $data): int|string
    {
        return is_numeric($data) ? 'number' : 'text';
    }

    public function forSubmittedData(mixed $data): int|string
    {
        return is_numeric($data) ? 'number' : 'text';
    }
}
