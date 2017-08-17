oagtoolbox
==========

[![Build Status](https://travis-ci.org/NeonOpenAG/oagtoolbox.svg?branch=master)](https://travis-ci.org/NeonOpenAG/oagtoolbox)

Install
-------

To run a standard lamp stack, you'll need to enter a sudo password at the end of the composer install.  This is to fix the permissions on the cache, var and upload folder.

    sudo apt install php7.0-sqlite realpath
    composer install

Tests
-----

    ./vendor/bin/simple-phpunit -c phpunit.xml --coverage-html web/test-coverage
