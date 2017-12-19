oagtoolbox
==========

[![Build Status](https://travis-ci.org/NeonOpenAG/oagtoolbox.svg?branch=master)](https://travis-ci.org/NeonOpenAG/oagtoolbox)

Install
-------

You will need php 7.1 at least.  Ubuntu 17.10 has it as default now, see here for updgrading on older machines:

    https://www.vultr.com/docs/how-to-install-and-configure-php-70-or-php-71-on-ubuntu-16-04

Composer will set everything up for you assuming you have a working lamp stack (7.1).  We need a couple of extra packages, sqlite, docker and compass:

```bash
sudo apt install php7.0-sqlite realpath ruby ruby-dev docker.io
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
