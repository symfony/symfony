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
 * @author Andrei Liutec <andrei@liutec.ro>
 */
interface FormTypeExtensionMultiInterface
{
    /**
     * Returns an array with the names of the types being extended.
     *
     * @return array The names of the types being extended.
     */
    public function getExtendedTypes();
}
