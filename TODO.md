### TO-DOs

For Luminova Framework version earlier thank (`v3.4.7`), the following changes are required:

- **Logger Configuration Update:** After updating framework using composer command. Add a new static method, `getEmailLogTemplate`, in the logger configuration class to provide a customizable HTML log email template. This will be used whenever the application is configured to send logs via email.

Here's an example of the updated `Logger` class:

```php
// /app/Config/Logger.php
namespace App\Config;

use Luminova\Base\BaseConfig;
use Luminova\Interface\HttpRequestInterface;

class Logger extends BaseConfig
{
    public static function getEmailLogTemplate(
        HttpRequestInterface $request, 
        string $message, 
        string $level, 
        array $context
    ): ?string 
    {
        return '<HTML-EMAIL-TEMPLATE>';
    }
}
```