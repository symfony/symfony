<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * @Annotation
 *
 * @api
 */
class Image extends File
{
    public $mimeTypes = 'image/*';
    public $mimeTypesMessage = 'This file is not a valid image';

    /**
     * @inheritDoc
     */
    public function validatedBy()
    {
        return __NAMESPACE__.'\FileValidator';
    }
}
