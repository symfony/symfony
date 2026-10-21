#!/usr/bin/env bash

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Runs the bash completion of a command line and prints, one per line, the value
# readline would insert for each suggestion.
#
# Usage: bash.sh <completion script> <command line> [<alias definition>]
#
# No "set -u" here: an interactive shell does not use it, and bash-completion 1.x
# relies on unset variables.

completion_script="$1"
command_line="$2"
alias_definition="$3"

# shellcheck disable=SC1091
source "$(dirname "$0")/bash-completion.sh"

# shellcheck disable=SC1090
source "$completion_script"

# the function is named after the command the script was dumped for
completion_function="$(grep -o '^_sf_[A-Za-z0-9_]*' "$completion_script" | head -n1)"

if [ -n "$alias_definition" ]; then
    shopt -s expand_aliases
    alias "$alias_definition"
fi

# Splits the command line the way readline does: on the characters of
# COMP_WORDBREAKS, which are words of their own, and on unquoted whitespace.
# Quotes and backslashes are kept, and they protect the characters they quote.
#
# Sets: words, cword, cur and prefix, the part of the current shell token that
# readline keeps in place, before the word being completed.
tokenize() {
    local line="$1" word='' token='' quote='' char i
    words=()

    for ((i = 0; i < ${#line}; i++)); do
        char="${line:i:1}"

        if [ -n "$quote" ]; then
            word+="$char" token+="$char"
            [ "$char" = "$quote" ] && quote=''
            continue
        fi

        case "$char" in
            \'|\")
                quote="$char"
                word+="$char" token+="$char"
                ;;
            \\)
                char+="${line:i+1:1}"
                word+="$char" token+="$char"
                i=$((i + 1))
                ;;
            ' '|$'\t')
                [ -n "$word" ] && words+=("$word")
                word='' token=''
                ;;
            *)
                if [[ "$COMP_WORDBREAKS" == *"$char"* ]]; then
                    [ -n "$word" ] && words+=("$word")
                    words+=("$char")
                    word='' token+="$char"
                else
                    word+="$char" token+="$char"
                fi
                ;;
        esac
    done

    words+=("$word")
    cword=$((${#words[@]} - 1))
    cur="$word"
    prefix="${token%"$word"}"
}

tokenize "$command_line"

COMP_WORDS=("${words[@]}")
COMP_CWORD=$cword
COMP_LINE="$command_line"
COMP_POINT=${#COMP_LINE}
COMPREPLY=()

# compopt is only available while a completion is running. The "filenames"
# option is recorded to print the suggestions as readline would insert them.
filenames=''
compopt() {
    local IFS=' '
    [[ " $* " == *' -o filenames '* ]] && filenames=1

    return 0
}

"$completion_function" || exit $?

# Undo the shell escaping and prepend what readline keeps, to print the value the
# user ends up with. With the "filenames" option, the suggestions are not escaped
# and readline adds a slash to the directories.
for suggestion in "${COMPREPLY[@]}"; do
    if [ -n "$filenames" ]; then
        [ -d "$suggestion" ] && suggestion="$suggestion/"
    else
        suggestion="$(eval "printf '%s' $suggestion" 2> /dev/null || printf '%s' "$suggestion")"
    fi
    printf '%s%s\n' "$prefix" "$suggestion"
done
