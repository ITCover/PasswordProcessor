# Password Processor

[![Build Status](https://travis-ci.org/ITCover/PasswordProcessor.svg?branch=master)](https://travis-ci.org/ITCover/PasswordProcessor)

## Examples

### Initialization

Implement a Data Access Object to access your password hashes' data source,
using `\ITCover\PasswordProcessor\DAOInterface`. Typically, this would be a
"users" table in your application's local database.

````
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
````

And then just pass that to our `Processor` class constructor:

````
<?php
use \Your\Namespace\UsersDAO;
use \ITCover\PasswordProcessor\Processor;

$pdo = new \PDO('mysql:dbname=foo;host=127.0.0.1', 'username', 'password');
$dao = new UsersDAO($pdo);

$passwordProcessor = new Processor($dao);
````

### Usage

```
// $passwordProcessor = new \ITCover\PasswordProcessor\Processor($dao);

// Creating a password for a new user:
$passwordHash = $passwordProcessor->createPassword($passwordInput);

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

````
$passwordProcessor = new Processor($dao, function($inputPassword) use ($salt) {
    return \hash('sha256', $inputPassword.$salt);
});
````

Any callables are accepted - from simple function names like 'sha1' (but
hopefully not that bad) and static class methods, to closures (anonymous
functions) and object methods. Just make sure the callback accepts a
string parameter and returns the hash as a string.
