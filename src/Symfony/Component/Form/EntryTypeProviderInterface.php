<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * Tells PolymorphicCollectionType which of its "entry_types" an entry should use.
 *
 * Both methods return one of the keys of the "entry_types" option. They are given the same
 * entry in two different shapes, hence the two methods: the model data before the form is
 * populated, and the raw submitted data.
 */
interface EntryTypeProviderInterface
{
    public function forModelData(mixed $data): int|string;

    public function forSubmittedData(mixed $data): int|string;
}
