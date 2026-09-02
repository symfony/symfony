#!/usr/bin/env bash

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Runs the bash completion of a command line and prints one logical suggestion per line.
#
# Usage: bash.sh <completion script> <command line>
#
# No "set -u" here: an interactive shell does not use it, and bash-completion 1.x
# relies on unset variables.

completion_script="$1"
command_line="$2"

# the Linux locations, then Homebrew's bash-completion@2 and 1.x
for bash_completion in \
    /usr/share/bash-completion/bash_completion \
    /etc/bash_completion \
    /opt/homebrew/etc/profile.d/bash_completion.sh \
    /opt/homebrew/etc/bash_completion \
    /usr/local/etc/profile.d/bash_completion.sh \
    /usr/local/etc/bash_completion \
; do
    if [ -f "$bash_completion" ]; then
        # shellcheck disable=SC1090
        source "$bash_completion"
        break
    fi
done

if ! declare -F _get_comp_words_by_ref > /dev/null; then
    echo "bash-completion is not installed" >&2
    exit 1
fi

# shellcheck disable=SC1090
source "$completion_script"

# Split the command line the way readline does: on unquoted whitespace, keeping the quotes.
tokenize() {
    local line="$1" token='' char quote='' i
    tokens=()
    for ((i = 0; i < ${#line}; i++)); do
        char="${line:i:1}"
        if [ -n "$quote" ]; then
            token+="$char"
            [ "$char" = "$quote" ] && quote=''
            continue
        fi
        case "$char" in
            \'|\")
                quote="$char"
                token+="$char"
                ;;
            ' ')
                tokens+=("$token")
                token=''
                ;;
            *)
                token+="$char"
                ;;
        esac
    done
    tokens+=("$token")
}

tokenize "$command_line"

COMP_WORDS=("${tokens[@]}")
COMP_CWORD=$((${#COMP_WORDS[@]} - 1))
COMP_LINE="$command_line"
COMP_POINT=${#COMP_LINE}
COMPREPLY=()

# compopt is only available while a completion is running
compopt() { :; }

"_sf_${COMP_WORDS[0]##*/}" || exit $?

# Undo the shell escaping added by the completion script to compare logical values
for suggestion in "${COMPREPLY[@]}"; do
    eval "printf '%s\n' $suggestion" 2> /dev/null || printf '%s\n' "$suggestion"
done
