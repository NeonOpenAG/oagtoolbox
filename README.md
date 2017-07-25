oagtoolbox
==========

A Symfony project created on July 5, 2017, 5:34 pm.

Install
-------

    composer install
    cp app/config/parameters.yml.sqlite app/config/parameters.yml
    ./bin/console doctrine:schema:create
    ./bin/console server:start
