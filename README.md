oagtoolbox
==========

[![Build Status](https://travis-ci.org/NeonOpenAG/oagtoolbox.svg?branch=master)](https://travis-ci.org/NeonOpenAG/oagtoolbox)

Install
-------

Composer will set everything up for you assuming you have a working lamp stack.  We need a couple of extra packages, sqlite and compass:

```bash
sudo apt install php7.0-sqlite realpath ruby ruby-dev
sudo gem install sass --no-user-install
```

You may need to enter a sudo password at the end of the composer install.  This is to fix the permissions on the cache, var and upload folder.

```bash
composer install
```

The install script will pull the unerlying dockers for you but won't start them.

```bash
composer run start-dockers
```

Tests
-----

    ./vendor/bin/simple-phpunit -c phpunit.xml --coverage-html web/test-coverage
