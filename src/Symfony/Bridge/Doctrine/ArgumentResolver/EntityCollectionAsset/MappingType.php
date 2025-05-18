<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionAsset;

enum MappingType: string
{
    case IGNORE = 'ignore';
    case LIMIT = 'limit';
    case OFFSET = 'offset';
    case PAGE = 'page';
    case NULL = 'null';
    case NOT_NULL = 'notNull';
    case CURRENT_USER_EXPRESSION = 'currentUserExpression';
}
