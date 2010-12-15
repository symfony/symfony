<?php

namespace Symfony\Component\Validator\Constraints;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class Image extends File
{
    public $mimeTypes = array(
        'image/png',
        'image/jpg',
        'image/jpeg',
        'image/pjpeg',
        'image/gif',
    );
    public $mimeTypesMessage = 'This file is not a valid image';

    /**
     * @inheritDoc
     */
    public function validatedBy()
    {
        return __NAMESPACE__.'\FileValidator';
    }
}
