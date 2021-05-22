# simple-php-model-system

![Lint and analyse files](https://github.com/wdes/simple-php-model-system/workflows/Lint%20and%20analyse%20files/badge.svg?branch=main)
![Run phpunit tests](https://github.com/wdes/simple-php-model-system/workflows/Run%20phpunit%20tests/badge.svg?branch=main)
[![codecov](https://codecov.io/gh/wdes/simple-php-model-system/branch/main/graph/badge.svg)](https://codecov.io/gh/wdes/simple-php-model-system)
![Packagist](https://img.shields.io/packagist/l/wdes/simple-php-model-system.svg)
[![Latest Stable Version](https://poser.pugx.org/wdes/simple-php-model-system/v/stable)](https://packagist.org/packages/wdes/simple-php-model-system)

A simple PHP model system

## Why ?

The goal of this project is to provide an easy way to use Models in a non composer setup.
This is why this project is kept very minimal.
The user can copy all classes in `src/` and start using the lib.
But this also works in a composer setup.

## How to use

```php
<?php

declare(strict_types = 1);

use SimplePhpModelSystem\Database;
use examples\User;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';// Use your autoloader or require classes by hand

$configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';// Copy config.dist.php and fill the values
$config     =  require $configFile;
$db         = new Database($config);
$db->connect();// Will throw an exception if not successfull

// Without models

$statement = $db->query(
    'SELECT 1'
);
$this->data = [];
while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
    $this->data[] = $row;
}
var_dump($this->data);


// With a model

$user1 = User::create(
    '5c8169b1-d6ef-4415-8c39-e1664df8b954',
    'Raven',
    'Reyes',
    null
);
$user1->save();// If you forget this line, it will only exist in memory
$user1 = User::findByUuid('5c8169b1-d6ef-4415-8c39-e1664df8b954');// Find the user back
$user1->toArray();
//[
//    'user_uuid' => '5c8169b1-d6ef-4415-8c39-e1664df8b954',
//    'first_name' => 'Raven',
//    'last_name' => 'Reyes',
//    'date_of_birth' => null,
//]
$user1->refresh(); // Get it back from the DB
$user1->set('last_name', 'Ali-2');// Change an attribute value
$user1->update();// Update it
$user1->delete();// Delete it
User::deleteAll();// Delete all
// And more functions, see AbstractModel class
```
