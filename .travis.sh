branch=$1
if [ -z "$branch" ]; then
    echo 'Usage: branch dir1 dir2 ... dirN'
    exit 1
fi
shift
components=$*
if [ -z "$components" ]; then
    echo 'Usage: branch dir1 dir2 ... dirN'
    exit 1
fi
echo '{"packages": {' > packages.json
for c in $components; do
    sed -i ':a;N;$!ba;s#^{\n\(\s*\)\("name"\)#{\n\1"repositories": \[{ "type": "composer", "url": "file://'$(pwd)'/" }\],\n\1\2#' $c/composer.json
    n=$(php -r '$n=json_decode(file_get_contents("'$c'/composer.json"));echo $n->name;')
    echo '"'$n'": {"'$branch'.x-dev": ' >> packages.json
    cat $c/composer.json >> packages.json
    echo '"version": "'$branch.x-dev'",\n    "dist": {"type": "tar", "url": "file://'$(pwd)/$c'/package'$branch'.tar"}\n}},' >> packages.json
done;
sed -i ':a;N;$!ba;s/\n}\n"/,\n    "/g' packages.json
sed -i ':a;N;$!ba;s/}},$/\n}}\n}}/' packages.json
for c in $components; do
    (cd $c && tar -cf package$branch.tar --exclude='package*.tar' *)
done
