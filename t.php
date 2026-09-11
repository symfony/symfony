<?php

function unescapeWildcard(string $index): string
{
$backslashes = strspn($index, '\\');

return 0 < $backslashes && $backslashes === \strlen($index) - 1 && '*' === $index[$backslashes] ? substr($index, 1) : $index;
}

var_dump(unescapeWildcard('\\\\*'));
var_dump(unescapeWildcard('\\\\\\\\*'));
var_dump(unescapeWildcard('\*'));
