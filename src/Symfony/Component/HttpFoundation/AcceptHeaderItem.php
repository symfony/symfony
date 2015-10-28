<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Header\AcceptItem;

@trigger_error('The '.__NAMESPACE__.'\AcceptHeaderItem class is deprecated since version 2.8 and will be removed in 3.0. Use the Symfony\Component\HttpFoundation\Header\AcceptItem class instead.', E_USER_DEPRECATED);

/**
 * Represents an Accept-* header item.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @deprecated since 2.8, to be removed in 3.0. Use Symfony\Component\HttpFoundation\Header\AcceptItem instead.
 */
class AcceptHeaderItem extends AcceptItem
{
}
