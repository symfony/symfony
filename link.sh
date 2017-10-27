#!/bin/bash
# Link dependencies to components to a local clone of the monolithic Symfony repository
# author: Kévin Dunglas

if [ $# -eq 0 ]
then
    echo "Link dependencies to components to a local clone of the monolithic Symfony repository"
    echo ""
    echo "Usage: ./link.sh /path/to/the/project"
    exit
fi

# Can't use maps because Mac OS doesn't ship Bash 4 by default...
declare -a dirs names

for dir in $(find $(pwd)/src/Symfony/{Bundle,Bridge,Component} -type d -maxdepth 1 -mindepth 1)
do
    dirs+=($dir)
    names+=($(cat $dir/composer.json | egrep -o '"name": "symfony/[a-zA-Z0-9-]+"' | egrep -o 'symfony/[a-zA-Z0-9-]+'))
done

for vendor in $(find $1/vendor/symfony -type d -maxdepth 1 -mindepth 1)
do
    vendorName=$(echo $vendor | rev | cut -sd / -f -2 | rev)
    for i in "${!names[@]}"
    do
        name=${names[$i]}

        if [ "$name" = "$vendorName" ]
        then
            mv $vendor $vendor.back
            ln -s ${dirs[$i]} $vendor
            echo "$vendorName linked to ${dirs[$i]}"
            continue
        fi
    done
done
