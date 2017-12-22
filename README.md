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

Permissions
-----------

The web interface for the toolkit needs to send data to and from the dockers so you will need to add the www-data user to the docker group and restart apache:

1. Add the www-data user to the docker group, edit ```/etc/group```

    docker:x:129:tobias,www-data

1. Restart Apache

    service apache2 restart

Tests
-----

| Test                                              | Description                                         |
|---------------------------------------------------|-----------------------------------------------------|
| [DFID-AF-MULTIPLE-CLASSIFICATION-SUGGESTIONS.xml](https://raw.githubusercontent.com/NeonOpenAG/oagtoolbox/develop/src/OagBundle/XMLTestFiles/afghanistan-based-tests/DFID-AF-MULTIPLE-CLASSIFICATION-SUGGESTIONS.xml) | Report contains multiple suggested classifications. |
| [DFID-AF-MULTIPLE-EXISTING-CLASSIFICATIONS.xml](https://raw.githubusercontent.com/NeonOpenAG/oagtoolbox/develop/src/OagBundle/XMLTestFiles/afghanistan-based-tests/DFID-AF-MULTIPLE-EXISTING-CLASSIFICATIONS.xml)   | Report contains multiple existing classifications.  |
| [DFID-AF-MULTIPLE-EXISTING-LOCATIONS.xml](https://raw.githubusercontent.com/NeonOpenAG/oagtoolbox/develop/src/OagBundle/XMLTestFiles/afghanistan-based-tests/DFID-AF-MULTIPLE-EXISTING-LOCATIONS.xml)         | Report contains multiple existing locations.        |
| [DFID-AF-MULTIPLE-LOCATION-SUGGESTIONS.xml](https://raw.githubusercontent.com/NeonOpenAG/oagtoolbox/develop/src/OagBundle/XMLTestFiles/afghanistan-based-tests/DFID-AF-MULTIPLE-LOCATION-SUGGESTIONS.xml)       | Report contains multiple suggested locations.       |
| [DFID-AF-SINGLE-CLASSIFICATION-SUGGESTION.xml](https://raw.githubusercontent.com/NeonOpenAG/oagtoolbox/develop/src/OagBundle/XMLTestFiles/afghanistan-based-tests/DFID-AF-SINGLE-CLASSIFICATION-SUGGESTION.xml)    | Report contains a single suggested clarification.   |
| [DFID-AF-SINGLE-EXISTING-LOCATION.xml](https://raw.githubusercontent.com/NeonOpenAG/oagtoolbox/develop/src/OagBundle/XMLTestFiles/afghanistan-based-tests/DFID-AF-SINGLE-EXISTING-LOCATION.xml)            | Report contains a single existing location.         |
| [DFID-AF-SINGLE-LOCATION-SUGGESTION.xml](https://raw.githubusercontent.com/NeonOpenAG/oagtoolbox/develop/src/OagBundle/XMLTestFiles/afghanistan-based-tests/DFID-AF-SINGLE-LOCATION-SUGGESTION.xml)          | Report contains a single suggested location.        |


    ./vendor/bin/simple-phpunit -c phpunit.xml --coverage-html web/test-coverage
