# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

_sf_{{ COMMAND_NAME }}() {

    # Use the default completion for shell redirect operators.
    for w in '>' '>>' '&>' '<'; do
        if [[ $w = "${COMP_WORDS[COMP_CWORD-1]}" ]]; then
            compopt -o filenames
            COMPREPLY=($(compgen -f -- "${COMP_WORDS[COMP_CWORD]}"))
            return 0
        fi
    done

    local sf_cmd="${COMP_WORDS[0]}"
    local sf_cmd_parts

    # for an alias, get the real command behind it, which may carry arguments
    sf_cmd_type=$(type -t $sf_cmd)
    if [[ $sf_cmd_type == "alias" ]]; then
        sf_cmd=$(alias $sf_cmd | sed -E "s/alias $sf_cmd='(.*)'/\1/")
        read -ra sf_cmd_parts <<< "$sf_cmd"
    elif [[ $sf_cmd_type == "file" ]]; then
        sf_cmd_parts=("$(type -p $sf_cmd)")
    else
        sf_cmd_parts=("$sf_cmd")
    fi

    # the command must be a function, an executable of the PATH or an executable file
    if [[ $sf_cmd_type != "function" ]] \
        && ! type -P "${sf_cmd_parts[0]}" > /dev/null \
        && [[ ! -x ${sf_cmd_parts[0]} ]] \
    ; then
        return 1
    fi

    # The bash-completion package provides the parsing of the current command line
    if ! declare -F _get_comp_words_by_ref > /dev/null; then
        >&2 echo "The completion of {{ COMMAND_NAME }} requires the \"bash-completion\" package to be installed and loaded."

        return 1
    fi

    # this must run with the default IFS: bash-completion 1.x, still shipped on
    # macOS, joins every word into a single one when IFS is a newline
    local cur prev words cword
    _get_comp_words_by_ref -n := cur prev words cword

    # Use newline as only separator to allow space in completion values
    local IFS=$'\n'

    local completecmd=("${sf_cmd_parts[@]}" "_complete" "--no-interaction" "-sbash" "-c$cword" "-a{{ VERSION }}")
    for w in "${words[@]}"; do
        w="${w//\\\\/\\}"
        # remove quotes from typed values
        quote="${w:0:1}"
        if [ "$quote" == \' ]; then
            w="${w%\'}"
            w="${w#\'}"
        elif [ "$quote" == \" ]; then
            w="${w%\"}"
            w="${w#\"}"
        fi
        # empty values are ignored
        if [ ! -z "$w" ]; then
            completecmd+=("-i$w")
        fi
    done

    local sfcomplete
    if sfcomplete=$(SHELL_VERBOSITY=0 "${completecmd[@]}" 2>&1); then
        local quote suggestions

        # for the "--option=value" form, the suggestions are the values: readline
        # keeps the "--option=" part, which is a word of its own for it
        if [[ "$cur" == -*=* ]]; then
            cur="${cur#*=}"
        fi

        quote=${cur:0:1}

        # Use single quotes by default if suggestions contains backslash (FQCN)
        if [ "$quote" == '' ] && [[ "$sfcomplete" =~ \\ ]]; then
            quote=\'
        fi

        if [ "$quote" == \' ]; then
            # single quotes: escape the single quotes contained in the values
            suggestions=$(for s in $sfcomplete; do
                s=${s//\'/\'\\\'\'}
                printf $'%q%q%q\n' "$quote" "$s" "$quote";
            done)
        elif [ "$quote" == \" ]; then
            # double quotes: double escaping for \ $ ` "
            suggestions=$(for s in $sfcomplete; do
                s=${s//\\/\\\\}
                s=${s//\$/\\\$}
                s=${s//\`/\\\`}
                s=${s//\"/\\\"}
                printf $'%q%q%q\n' "$quote" "$s" "$quote";
            done)
        else
            # no quotes: double escaping
            suggestions=$(for s in $sfcomplete; do printf $'%q\n' $(printf '%q' "$s"); done)
        fi
        COMPREPLY=($(IFS=$'\n' compgen -W "$suggestions" -- $(printf -- "%q" "$cur")))
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
