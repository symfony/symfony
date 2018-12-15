<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$operators = ['not', '!', 'or', '||', '&&', 'and', '|', '^', '&', '==', '===', '!=', '!==', '<', '>', '>=', '<=', 'not in', 'in', '..', '+', '-', '~', '*', '/', '%', 'matches', '**'];
$operators = array_combine($operators, array_map('strlen', $operators));
arsort($operators);

$regex = [];
foreach ($operators as $operator => $length) {
    // an operator that ends with a character must be followed by
    // a whitespace or a parenthesis
    $regex[] = preg_quote($operator, '/').(ctype_alpha($operator[$length - 1]) ? '(?=[\s(])' : '');
}

echo '/'.implode('|', $regex).'/A';
