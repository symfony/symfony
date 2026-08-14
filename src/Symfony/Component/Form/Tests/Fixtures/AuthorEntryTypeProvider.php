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

class AuthorEntryTypeProvider implements EntryTypeProviderInterface
{
    public function forModelData(mixed $data): int|string
    {
        return $data instanceof Author ? 'author' : 'text';
    }

    public function forSubmittedData(mixed $data): int|string
    {
        return \is_array($data) ? 'author' : 'text';
    }
}
