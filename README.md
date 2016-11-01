# Password Processor

[![Build Status](https://travis-ci.org/ITCover/PasswordProcessor.svg?branch=master)](https://travis-ci.org/ITCover/PasswordProcessor)

Framework-agnostic password-processing library for PHP.

## About

Just a little more than a wrapper around PHP's own
[password_*()](https://secure.php.net/password) functions.

Currently using [bcrypt](https://en.wikipedia.org/wiki/Bcrypt) with a work
factor of 11, but both of these will be updated in the future, as more
strength becomes necessary.

For new development, it just does the lower-level function calls,
abstracting password hashing away from your business logic and giving you
one architectural problem less to worry about.

For older applications, it also offers a painless way to upgrade your old
password hashing algorithms to a modern one.

**Note:** *This is NOT a fully-featured authentication, authorization or
          ACL library! It will only ever deal with creating, verifying and
          updating password hashes.*

### Motivation

PHP's [password extension](https://secure.php.net/password) is really great,
but it is also still "just" a language primitive - it provides the tools,
not the complete solution. As it should be.

This library is that complete solution.

It is designed to hook into your application, not the other way around,
so you don't need to worry about how to abstract it.
It offers a seamless way to migrate from *any* legacy hashing algorithm,
so you don't have to think about that either.
It is opinionated and intentionally leaves out any custom options, so
there's only one way to use it, no unsafe choices.

## Installation

PHP 5.6 or newer is required. The latest stable version of PHP is always recommended.

### Via [Composer](https://getcomposer.org/) (the easy and recommended way)

```
composer require itcover/password-processor
```

### Manual (you need to know what you're doing)

`git clone` or download and extract an archived version from
[here](https://github.com/ITCover/PasswordProcessor/releases)
and `require` the *autoload.php* file.

## Examples

### Initialization

Implement a Data Access Object to access your password hashes' data source,
using `\ITCover\PasswordProcessor\DAOInterface`. Typically, this would be a
"users" table in your application's local database.

```php
<?php
namespace Your\Namespace;

use \ITCover\PasswordProcessor\DAOInterface as DAOInterface;

class UsersDAO implements DAOInterface
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPasswordHashForIdentity($identity)
    {
        $query = $this->pdo->prepare("SELECT password FROM users WHERE username = :username");
        $query->execute([':username' => $identity]);
        $result = $query->fetch(\PDO::FETCH_ASSOC);
        return empty($result) ? null : $result['password'];
    }

    public function setPasswordHashForIdentity($identity, $passwordHash)
    {
        $query = $this->pdo->prepare("UPDATE users SET password = :password WHERE username = :username");
        $query->execute([
            ':password' => $passwordHash,
            ':username' => $identity
        ]);
    }
}
```

And then just pass that to our `Processor` class constructor:

```php
<?php
use \Your\Namespace\UsersDAO;
use \ITCover\PasswordProcessor\Processor;

$pdo = new \PDO('mysql:dbname=foo;host=127.0.0.1', 'username', 'password');
$dao = new UsersDAO($pdo);

$passwordProcessor = new Processor($dao);
```

Obviously, your application logic would be a little more complex than that,
and we're only using [PDO](https://secure.php.net/pdo) as an example here,
but all you really need to use the `Processor` class is an object
implementing our `DAOInterface`.

### Usage

```php
// $passwordProcessor = new \ITCover\PasswordProcessor\Processor($dao);

// Creating a password for a new user:
$passwordHash = $passwordProcessor->hashPassword($passwordInput);

// Updating a user's password:
$passwordProcessor->updatePassword($username, $password);

// Verifying, and AUTOMATICALLY UPDATING (re-hashing) a user's password
// (your typical login scenario)
if ($passwordProcessor->verifyPassword($username, $password))
{
    // login logic here
}
else
{
    // log failures, apply rate-limits, redirect back to login screen, etc.
}
```

### Upgrading from a legacy hash function

Simply pass your old hash function as a callable to the constructor:

```php
$passwordProcessor = new Processor($dao, function($inputPassword) use ($salt) {
    return \hash('sha256', $inputPassword.$salt);
});
```

Any callables are accepted - from simple function names like 'sha1' (but
hopefully not that bad) and static class methods, to closures (anonymous
functions) and object methods. Just make sure the callback accepts a
string parameter and returns the hash as a string.
