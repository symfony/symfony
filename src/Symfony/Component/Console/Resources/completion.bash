# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

_sf_{{ COMMAND_NAME }}() {
    local sf_cmd="${COMP_WORDS[0]}"
    if [ ! -f "$sf_cmd" ]; then
        return 1
    fi

    local cur prev words cword
    _get_comp_words_by_ref -n := cur prev words cword

    local completecmd=("$sf_cmd" "_complete" "-sbash" "-c$cword" "-S{{ VERSION }}")
    for w in ${words[@]}; do
        completecmd+=(-i "'$w'")
    done

    local sfcomplete
    if sfcomplete=$(${completecmd[@]} 2>&1); then
        COMPREPLY=($(compgen -W "$sfcomplete" -- "$cur"))
        __ltrim_colon_completions "$cur"
    else
        if [[ "$sfcomplete" != *"Command \"_complete\" is not defined."* ]]; then
            >&2 echo
            >&2 echo $sfcomplete
        fi

        return 1
    fi
}

complete -F _sf_{{ COMMAND_NAME }} {{ COMMAND_NAME }}
