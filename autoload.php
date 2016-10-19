<?php
/**
 * A simple PSR-4 autoloader for manual setups
 *
 * @author  Andrey Andreev <aandreev@it-cover.com>
 * @author  Andrey Andreev <narf@devilix.net>
 * @license https://github.com/ITCover/PublicLicense/blob/1.0/LICENSE.txt
 *          IT-Cover Public License, version 1.0
 *
 * @link    http://www.php-fig.org/psr/psr-4/   PSR-4
 */
spl_autoload_register(function($class) {

    static $namespace = 'ITCover\\PasswordProcessor\\';
    static $directory = __DIR__.\DIRECTORY_SEPARATOR.'src/';

    if (\sscanf($class, "{$namespace}%s", $search) !== 1) {
        return;
    } elseif (\DIRECTORY_SEPARATOR !== '\\') {
        $search = str_replace('\\', \DIRECTORY_SEPARATOR, $search);
    }

    if (\is_file($directory.$search.'.php')) {
        require_once $directory.$search.'.php';
    }
});
