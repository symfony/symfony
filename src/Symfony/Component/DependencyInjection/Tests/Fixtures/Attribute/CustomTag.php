<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute;

use Symfony\Contracts\Service\Attribute\TagInterface;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class CustomTag implements TagInterface
{
    public function getName(): string
    {
        return 'app.custom_tag';
    }

    public function getAttributes(): array
    {
        return ['someAttribute' => 'custom_tag_class'];
    }
}
