#!/usr/bin/env bash

cd $(dirname $BASH_SOURCE)
php -dzend_extension=xdebug.so vendor/bin/phpunit --no-configuration --coverage-text --whitelist=src/ tests/

