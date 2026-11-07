<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Microsoft;

enum CodeLanguage: string
{
    case Bash = 'Bash';
    case C = 'C';
    case CSharp = 'CSharp';
    case Cpp = 'Cpp';
    case Css = 'Css';
    case Dos = 'Dos';
    case Go = 'Go';
    case Graphql = 'Graphql';
    case Html = 'Html';
    case Java = 'Java';
    case JavaScript = 'JavaScript';
    case Json = 'Json';
    case ObjectiveC = 'ObjectiveC';
    case Perl = 'Perl';
    case Php = 'Php';
    case PowerShell = 'PowerShell';
    case Python = 'Python';
    case Sql = 'Sql';
    case TypeScript = 'TypeScript';
    case VbNet = 'VbNet';
    case Verilog = 'Verilog';
    case Vhdl = 'Vhdl';
    case Xml = 'Xml';
}
