<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\CacheWarmer;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;

final class DefinitionAndValidator
{
    public function __construct(
        public readonly DefinitionValidatorInterface $validator,
        public readonly Definition $definition,
        public readonly string $name,
    ) {
    }
}
