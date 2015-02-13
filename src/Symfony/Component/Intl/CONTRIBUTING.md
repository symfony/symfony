Contributing to the Intl component
==================================

A very good way of contributing to the Intl component is by updating the
included data for the ICU version you have installed on your system.

Preparation
-----------

To prepare, you need to install the development dependencies of the component.

    $ cd /path/to/Symfony/Component/Intl
    $ composer install

Determining your ICU version
---------------------------

The ICU version installed in your PHP environment can be found by running
icu-version.php:

    $ php Resources/bin/icu-version.php

Updating the ICU data
---------------------

To update the data files, run the update-icu-component.php script:

    $ php Resources/bin/update-icu-component.php

The script needs the binaries "svn" and "make" to be available on your system.
It will download the latest version of the ICU sources for the ICU version
installed in your PHP environment. The script will then compile the "genrb"
binary and use it to compile the ICU data files to binaries. The binaries are
copied to the Resources/ directory of the Icu component found in the
vendor/symfony/icu/ directory.

Updating the stub data
----------------------

In the previous step you updated the Icu component for the ICU version
installed on your system. If you are using the latest ICU version, you should
also create the stub data files which will be used by people who don't have
the intl extension installed.

To update the stub files, run the update-stubs.php script:

    $ php Resources/bin/update-stubs.php

The script will fail if you don't have the latest ICU version. If you want to
upgrade the ICU version, adjust the return value of the
`Intl::getIcuStubVersion()` before you run the script.

The script creates copies of the binary resource bundles in the Icu component
and stores them in the Resources/ directory of the Intl component. The copies
are made for the locale "en" only and are stored in .php files, so that they
can be read even if the intl extension is not available.

Creating a pull request
-----------------------

You need to create up to two pull requests:

* If you updated the Icu component, you need to push that change and create a
  pull request in the `symfony/Icu` repository. Make sure to submit the pull
  request to the correct master branch. If you updated the ICU data for version
  4.8, your pull request goes to branch `48-master`, for version 49 to
  `49-master` and so on.

* If you updated the stub files of the Intl component, you need to push that
  change and create a pull request in the `symfony/symfony` repository. The
  pull request should be based on the `master` branch.

Combining .res files to a .dat-package
--------------------------------------

The individual *.res files can be combined into a single .dat-file.
Unfortunately, PHP's `ResourceBundle` class is currently not able to handle
.dat-files.

Once it is, the following steps have to be followed to build the .dat-file:

1. Package the resource bundles into a single file

   $ find . -name *.res | sed -e "s/\.\///g" > packagelist.txt
   $ pkgdata -p region -T build -d . packagelist.txt

2. Clean up

   $ rm -rf build packagelist.txt

3. You can now move region.dat to replace the version bundled with Symfony.
