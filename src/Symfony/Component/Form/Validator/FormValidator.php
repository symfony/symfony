<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Validator;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FieldError;

class FormValidator implements FieldValidatorInterface
{
    public function validate(FieldInterface $form)
    {
        if (count($form->getExtraData()) > 0) {
            $form->addError(new FieldError('This form should not contain extra fields'));
        }

        if ($form->isRoot() && isset($_SERVER['CONTENT_LENGTH'])) {
            $length = (int) $_SERVER['CONTENT_LENGTH'];
            $max = trim(ini_get('post_max_size'));

            switch (strtolower(substr($max, -1))) {
                // The 'G' modifier is available since PHP 5.1.0
                case 'g':
                    $max *= 1024;
                case 'm':
                    $max *= 1024;
                case 'k':
                    $max *= 1024;
            }

            if ($length > $max) {
                $form->addError(new FieldError('The uploaded file was too large. Please try to upload a smaller file'));
            }
        }
    }
}