#!/bin/bash

#######################################
# Fold Travis CI output
# Arguments:
#   $1 fold name
#   $2 command to execute
#######################################
tfold () {
    FOLD=$(echo $1 | tr / .)
    echo "travis_fold:start:$FOLD"
    echo -e "\\e[32m$FOLD\\e[0m"
    sh -c "$2" && echo "travis_fold:end:$FOLD"
}

export -f tfold
