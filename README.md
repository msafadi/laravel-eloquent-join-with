# Laravel Eloquent JoinWith

Laravel Eloquent Join With is a package that simplifies performing efficient database joins on existing Eloquent relationships of type `HasOne` and `BelongsTo`. By utilizing these relationships, JoinWith optimizes performance by executing a single query instead of the two separate queries typically required with the standard `with` method. This translates to faster and more efficient data retrieval.

## Installation

### Requirements

-   Laravel version: ^8.x (or later)

### Using Composer

Run `composer require msafadi/laravel-eloquent-join-with` to install the package.

## Usage

There are two ways to use Laravel JoinWith in your application models:

### 1. Include the Trait

You can include the `JoinWith` trait provided by the package in your application models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Msafadi\EloquentJoinWith\Database\Concerns\JoinWith;

class User extends Model
{
    use JoinWith;

    // ... other model properties and methods
}
```

With the trait included, you can then use the `joinWith` method directly on your model queries.

### 2. Extend the Model Class

Alternatively, you can extend your model classes with `Msafadi\LaravelJoinWith\Database\Eloquent\Model`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Msafadi\EloquentJoinWith\Database\Eloquent\Model as JoinWithModel;

class User extends JoinWithModel
{
    // ... other model properties and methods
}
```

This approach also grants access to the `joinWith` method on your model queries.

### Basic Usage

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

### Advanced Usage

For more complex scenarios, you can pass a closure to the `joinWith` method to customize the join conditions:

```php
$orders = Orders::joinWith('user', function ($query) {
    $query->where('users.status', '=', 'verified');
})
->get();

// This will execute a single query joining orders and users tables
// based on the BelongsTo relationship and the additional where clause.
```

This example retrieves orders that belongs to a verified user, combining the user and order information in a single query.

## Important Note

-   Laravel JoinWith currently works with HasOne and BelongsTo relationships. Support for other relationship types might be added in future versions.
-   Nested relations is not supported yet but might be added in future versions.

## Contributing

We welcome contributions to this package! If you'd like to contribute, please feel free to open a pull request.

## License

This package is distributed under the MIT License.
