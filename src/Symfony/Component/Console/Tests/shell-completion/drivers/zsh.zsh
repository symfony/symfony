#!/usr/bin/env zsh

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Runs the zsh completion of a command line and prints one logical suggestion per line.
#
# The completion widget is called directly, with _describe stubbed to print the
# candidates instead of displaying them. This keeps the test deterministic while
# still running the real completion script.
#
# Usage: zsh.zsh <completion script> <command line>

emulate -L zsh
setopt no_nomatch

local completion_script="$1"
local command_line="$2"
local separator=$'\1'

local -a words
words=("${(z)command_line}")
if [[ "$command_line" == *' ' ]]; then
    words+=('')
fi
local CURRENT=$#words

# the word being completed, without the quotes typed by the user
local current_word="${words[CURRENT]}"
current_word="${current_word#[\'\"]}"

# _describe is called as: _describe "completions" <array name> [-P <prefix>]
# It hands the candidates over to compadd, which only keeps the ones matching
# the word being completed. The stub does the same filtering.
_describe() {
    local -a candidates
    candidates=("${(@P)2}")
    local prefix='' candidate
    if [[ "$3" == '-P' ]]; then
        prefix="$4"
    fi

    for candidate in $candidates; do
        # keep the value only, the description is separated by an unescaped colon
        candidate="${candidate//\\:/$separator}"
        candidate="${candidate%%:*}"
        candidate="${candidate//$separator/:}"

        if [[ "$candidate" == "${current_word#$prefix}"* ]]; then
            print -r -- "$candidate"
        fi
    done
}

# the completion script is a compdef file, ignore the compdef call
compdef() { :; }

source "$completion_script"

"_sf_${${words[1]}:t}"
