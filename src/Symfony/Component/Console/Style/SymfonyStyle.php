<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Style;

/**
 * Output decorator helpers for the Symfony Style Guide.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @final since Symfony 5.4
 */
class SymfonyStyle extends BaseOutputStyle
{
    protected const TITLE_CHAR = '=';
    protected const SECTION_CHAR = '-';
    protected const LISTING_CHAR = '*';
    protected const COMMENT_STYLE = '<fg=default;bg=default> // </>';
    protected const SUCCESS_STYLE = 'fg=black;bg=green';
    protected const ERROR_STYLE = 'fg=white;bg=red';
    protected const WARNING_STYLE = 'fg=black;bg=yellow';
    protected const NOTE_STYLE = 'fg=yellow';
    protected const INFO_STYLE = 'fg=green';
}
