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
# Usage: bash.sh <completion script> <command line>
#
# No "set -u" here: an interactive shell does not use it, and bash-completion 1.x
# relies on unset variables.

completion_script="$1"
command_line="$2"

# shellcheck disable=SC1091
source "$(dirname "$0")/bash-completion.sh"

# shellcheck disable=SC1090
source "$completion_script"

# Splits the command line the way readline does: on the characters of
# COMP_WORDBREAKS, which are words of their own, and on unquoted whitespace.
# Quotes are kept, and open quotes protect the characters they contain.
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

# compopt is only available while a completion is running
compopt() { :; }

"_sf_${COMP_WORDS[0]##*/}" || exit $?

# Undo the shell escaping and prepend what readline keeps, to print the value the
# user ends up with
for suggestion in "${COMPREPLY[@]}"; do
    suggestion="$(eval "printf '%s' $suggestion" 2> /dev/null || printf '%s' "$suggestion")"
    printf '%s%s\n' "$prefix" "$suggestion"
done
