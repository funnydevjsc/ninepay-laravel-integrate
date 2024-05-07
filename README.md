# 9pay.vn Laravel

The free Laravel package to help you integrate payment with 9pay.vn

## Use Cases

- Create a payment link with 9pay.vn
- Parse result from 9pay.vn
- Example webhook

## Features

- Dynamic 9pay.vn credentials from config/ninepay.php
- Easy to create payment link with a simple line code

## Requirements

- **PHP**: 8.1 or higher
- **Laravel** 9.0 or higher

## Quick Start

If you prefer to install this package into your own Laravel application, please follow the installation steps below

## Installation

#### Step 1. Install a Laravel project if you don't have one already

https://laravel.com/docs/installation

#### Step 2. Require the current package using composer:

```bash
composer require funnydevjsc/ninepay-laravel-integrate
```

#### Step 3. Publish the controller file and config file

```bash
php artisan vendor:publish --provider="FunnyDev\Ninepay\NinepayServiceProvider" --tag="ninepay"
```

If publishing files fails, please create corresponding files at the path `config/ninepay.php` and `app\Http\Controllers\NinepayControllers.php` from this package. And you can also further customize the NinepayControllers.php file to suit your project.

#### Step 4. Update the various config settings in the published config file:

After publishing the package assets a configuration file will be located at <code>config/ninepay.php</code>. Please contact 9pay.vn to get those values to fill into the config file.

#### Step 5. Add middleware protection:

###### app/Http/Kernel.php

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // Other kernel properties...
    
    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $routeMiddleware = [
        // Other middlewares...
         'ninepay' => 'FunnyDev\Ninepay\Http\Middleware\NinepayMiddleware',
    ];
}
```

#### Step 6. Add route:

###### routes/api.php

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NinepayController;

// Other routes properties...

Route::group(['middleware' => ['ninepay']], function () {
    Route::post('/ninepay/webhook', [NinepayController::class, 'webhook']);
});

}
```

Then your IPN (Webhook) URL will be something like https://yourdomain.ltd/api/ninepay/webhook, and you should provide it to 9pay's account setting. You could provide it to `routes/web.php` if you want but remember that 9pay will check for referer matched with the pre-registration URL. So make sure that you provide them the right URL of website.

<!--- ## Usage --->

## Testing

``` php
<?php

namespace App\Console\Commands;

use FunnyDev\Ninepay\NinepaySdk;
use Illuminate\Console\Command;

class NinepayTestCommand extends Command
{
    protected $signature = 'ninepay:test';

    protected $description = 'Test Ninepay SDK';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $instance = new NinepaySdk();
        echo $instance->create_payment(
            'INV-test-01',
            10000,
            'Description-test-01',
            'http://localhost:8000/return',
            'http://localhost:8000/back'
        );
    }
}
```

## Feedback

Respect us in the [Laravel Việt Nam](https://www.facebook.com/groups/167363136987053)

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email contact@funnydev.vn or use the issue tracker.

## Credits

- [Funny Dev., Jsc](https://github.com/funnydevjsc)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
