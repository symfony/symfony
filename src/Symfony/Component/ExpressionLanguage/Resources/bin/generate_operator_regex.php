<?php

$operators = array('not', '!', 'or', '||', '&&', 'and', '|', '^', '&', '==', '===', '!=', '!==', '<', '>', '>=', '<=', 'not in', 'in', '..', '+', '-', '~', '*', '/', '%', 'matches', '**');
$operators = array_combine($operators, array_map('strlen', $operators));
arsort($operators);

$regex = array();
foreach ($operators as $operator => $length) {
    // an operator that ends with a character must be followed by
    // a whitespace or a parenthesis
    $regex[] = preg_quote($operator, '/').(ctype_alpha($operator[$length - 1]) ? '(?=[\s(])' : '');
}

echo '/'.implode('|', $regex).'/A';
