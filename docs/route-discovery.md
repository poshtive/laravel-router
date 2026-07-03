# Route Discovery

Route discovery scans controller classes and registers Laravel routes from their public methods.

## Registering Discovery

Add discovery to `routes/web.php`:

```php
use Poshtive\Router\Router;

Router::create()->discover(app_path('Http/Controllers'));
```

## Route Shape

In general, discovered URIs are built from folder names, controller names, method names, and method parameters:

```text
/folder-one/folder-two/controller-name/{parameter-1}/method-name/{parameter-2}
```

Controller, method, and folder names are converted to kebab-case.

## Public Methods

Only public methods are considered.

Inherited methods are included only when `method_extends` is set to `true` in `config/router.php`.

## Index Controllers and Methods

`IndexController` is omitted from the URI. A method named `index` is also omitted.

```php
namespace App\Http\Controllers;

class IndexController
{
    public function index() {}

    public function about() {}
}
```

This registers:

- `GET /`
- `GET /about`

```php
namespace App\Http\Controllers;

class UserController
{
    public function index() {}

    public function show() {}
}
```

This registers:

- `GET /user`
- `GET /user/show`

```php
namespace App\Http\Controllers\Admin;

class IndexController
{
    public function index() {}

    public function dashboard() {}
}
```

This registers:

- `GET /admin`
- `GET /admin/dashboard`

## Parameters and Model Binding

Method parameters become route parameters in the order they appear in the method signature.

Only primitive `int`, primitive `string`, and classes extending `Illuminate\Database\Eloquent\Model` are considered route parameters.

```php
namespace App\Http\Controllers;

use App\Models\User;

class UserController
{
    public function show(int $id) {}

    public function edit(User $user) {}
}
```

This registers:

- `GET /user/{id}/show`
- `GET /user/{user}/edit`

Laravel receives `{user}` as a route parameter for model binding.

## Parameter Order

By default, the first route parameter is placed before the method segment.

```php
class UserController
{
    public function update(int $id, string $section) {}
}
```

This registers:

```text
GET /user/{id}/update/{section}
```

Use `keepOrder: true` to keep all route parameters after the method segment:

```php
use Poshtive\Router\Attributes\Route;

class UserController
{
    #[Route(keepOrder: true)]
    public function update(int $id, string $section) {}
}
```

This registers:

```text
GET /user/update/{id}/{section}
```

`keepOrder` can also be applied at the class level:

```php
use Poshtive\Router\Attributes\Route;

#[Route(keepOrder: true)]
class UserController
{
    public function update(int $id, string $section) {}
}
```

## Child Controllers

When a folder name matches a controller name without the `Controller` suffix, controllers inside that folder are treated as child controllers.

Given this structure:

```text
app/Http/
└── Controllers/
    ├── UserController.php
    └── User/
        ├── ProfileController.php
        └── SettingsController.php
```

Routes are registered as:

- `UserController` methods: `/user/{parameter}/method-name`
- `ProfileController` methods: `/user/{parameter1}/profile/{parameter2}/method-name`
- `SettingsController` methods: `/user/{parameter1}/settings/{parameter2}/method-name`

Controller methods inside the `User` folder must have at least one parameter that extends `Illuminate\Database\Eloquent\Model`. Registration fails when the required parent model parameter is missing.

## Duplicate Routes

When duplicate route names or duplicate `HTTP_VERB + URI` combinations are discovered, behavior depends on the `strict` config value:

- `false`: duplicates are reported to the logger when available.
- `true`: duplicates throw an exception and stop registration.
