#!/usr/bin/env fish

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Runs the fish completion of a command line and prints one logical suggestion per line.
#
# Usage: fish.fish <completion script> <command line> [<alias definition>]

source $argv[1]

# an alias is a function wrapping the command, fish reuses its completions
if set -q argv[3]
    alias (string split -m1 '=' -- $argv[3])
end

# fish returns "value<TAB>description", keep the value only
for suggestion in (complete --do-complete=$argv[2])
    echo (string split -f1 \t -- $suggestion)
end
