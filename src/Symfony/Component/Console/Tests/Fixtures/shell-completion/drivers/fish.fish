#!/usr/bin/env fish

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Runs the fish completion of a command line and prints one logical suggestion per line.
#
# Usage: fish.fish <completion script> <command line>

source $argv[1]

# fish returns "value<TAB>description", keep the value only
for suggestion in (complete --do-complete=$argv[2])
    echo (string split -f1 \t -- $suggestion)
end
