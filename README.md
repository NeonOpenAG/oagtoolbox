oagtoolbox
==========

[![Build Status](https://travis-ci.org/NeonOpenAG/oagtoolbox.svg?branch=master)](https://travis-ci.org/NeonOpenAG/oagtoolbox)

Install
-------

When you clone the repository you want to initialize any submodules. You can achieve this with a recursive flag:

```bash
git clone --recursive git://github.com/foo/bar.git
```

If you have a pre-existing repo without the submodules initialized you can achieve this with the following command:

```bash
git submodule update --init --recursive
```

To run a standard lamp stack, you'll need to enter a sudo password at the end of the composer install.  This is to fix the permissions on the cache, var and upload folder.

    sudo apt install php7.0-sqlite realpath
    composer install

Tests
-----

    ./vendor/bin/simple-phpunit -c phpunit.xml --coverage-html web/test-coverage
