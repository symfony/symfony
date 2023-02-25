<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UploadedFileValueResolver;

/**
 * Controller parameter tag to map uploaded files.
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class MapUploadedFile extends ValueResolver
{
    public function __construct(
        public readonly ?string $name = null,
        string $resolver = UploadedFileValueResolver::class,
    ) {
        parent::__construct($resolver);
    }
}
