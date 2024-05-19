# Laravel Eloquent JoinWith

Laravel Eloquent Join With is a package that simplifies performing efficient database joins on existing Eloquent relationships of type `HasOne` and `BelongsTo`. By utilizing these relationships, JoinWith optimizes performance by executing a single query instead of the two separate queries typically required with the standard `with` method. This translates to faster and more efficient data retrieval.

## Installation

You can install the package via composer:

```console
composer require msafadi/laravel-eloquent-join-with
```

## Usage

There are two ways to use Laravel JoinWith in your application models:

### 1. Use `JoinWith` Trait

Include the `JoinWith` trait provided by the package in your application models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Safadi\EloquentJoinWith\Database\Concerns\JoinWith;

class User extends Model
{
    use JoinWith;

    // ... other model properties and methods
}
```

With the trait included, you can then use the `joinWith` method directly on your model queries.

### 2. Extend the Model Class

Alternatively, you can extend your model classes with `Safadi\EloquentJoinWith\Database\Eloquent\Model`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Safadi\EloquentJoinWith\Database\Eloquent\Model as JoinWithModel;

class User extends JoinWithModel
{
    // ... other model properties and methods
}
```

This approach also grants access to the `joinWith` method on your model queries.

### Usage Examples

Once you've integrated Laravel JoinWith into your models, you can use the `joinWith` method on your Eloquent model queries.

The defintion and usage of `joinWith` is exactly the same as using the standard `with` method. But it will execute a single query joining the parent model and the realted model tables instead of fetching realted models with executing an extra database query. Here's an example:

```php
$user = User::joinWith('profile')
            ->select('users.id', 'users.name')
            ->first();

// This will execute a single query joining the users and profiles tables
// based on the defined HasOne relationship between User and Profile models.
```

This code retrieves the user information along with the associated profile's avatar in a single query.

As the standard `with` method, you can use nested realtions syntax:

```php
$user = User::joinWith('profile.country')
            ->first();

// This will execute a single query joining the users, profiles, and countries tables
// based on the defined HasOne relationship between User and Profile and between Profile and Country models.
```

For more complex scenarios, you can pass a closure to the `joinWith` method to customize the join conditions, similar to the standard `with` method:

```php
$orders = Orders::joinWith(['user' => function ($query) {
    $query->where('users.status', '=', 'verified');
}])
->get();

// This will execute a single query joining orders and users tables
// based on the BelongsTo relationship and the additional where clause.
```

This example retrieves orders that belongs to a verified user, combining the user and order information in a single query.

## Limitations

-   Laravel JoinWith currently works with `HasOne` and `BelongsTo` relationships. Support for other relationship types might be added in future versions.
-   Specifying which columns of the relationship you would like to retrieve is not supported yet but might be added in future versions.

## Contributing

We welcome contributions to this package! If you'd like to contribute, please feel free to open a pull request.

## License

This package is distributed under the MIT License.
