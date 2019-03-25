<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Extractor;

/**
 * Extracts write mutator for property of a class.
 *
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface WriteMutatorExtractorInterface
{
    public function getWriteMutator(string $class, string $property, bool $allowConstructor = true): ?WriteMutator;
}
