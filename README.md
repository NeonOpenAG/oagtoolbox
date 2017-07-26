oagtoolbox
==========

[![Build Status](https://travis-ci.org/NeonOpenAG/oagtoolbox.svg?branch=master)](https://travis-ci.org/NeonOpenAG/oagtoolbox)

Install
-------

    sudo apt install php7.0-sqlite pecl
    sudo pecl install runkit

    composer install
    cp app/config/parameters.yml.sqlite app/config/parameters.yml
    ./bin/console doctrine:schema:create
    ./bin/console server:start

To run in apache you'll need some permissions foo

    HTTPDUSER=$(ps axo user,comm | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1)
    sudo setfacl -dR -m u:"$HTTPDUSER":rwX -m u:$(whoami):rwX var app/*.db web/*/oagfiles
    sudo setfacl -R -m u:"$HTTPDUSER":rwX -m u:$(whoami):rwX var app/*.db web/*/oagfiles

Tests
-----

    ./vendor/bin/simple-phpunit -c phpunit.xml --coverage-html web/test-coverage
