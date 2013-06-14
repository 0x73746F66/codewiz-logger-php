Codewiz_Logger
==============

A PHP logging API that also automatically catches all uncaught errors, also compatible with Zend Framework

Drop the "Codewiz" folder in your zend library directory, or if not using ZF drop it into your include path.

If using ZF your autoloader will have already been configured, otherwise ensure you require_once the Logger/php file.

To configure and use the auto-magical functionality - create an instance in your bootstrap or equivelant;

```php
$logger = new Codewiz_Logger( array(
    "enableHandler" => array(
        "error"     => true,
        "exception" => true,
        "fatal"     => true
    )
) , true );

```

This will register the handlers.

Use use an instance of Logger with any of the methods any time, they are all well documented (PHPDoc) and look great with NetBeans IntelliSense.

If you need to de-register the handlers and restore your environment at any time call restoreEnvironment(). Your original error_reporting and display_errors settings are detected and reapplied.
If you feel the need to define the restore values they are configurable.

Configure manually in the class, via the constructor, or INI. Values are;
```ini
logger.adminEmail = "chris@codewiz.biz"
logger.dateFormat = "Y-m-d h:i:s"
logger.emails.content = "html"
logger.emails.details = true
logger.enableHandler.error = true
logger.enableHandler.exception = true
logger.enableHandler.fatal = true
logger.global.display_errors = "off"
logger.global.error_reporting = "8"
logger.destination.default[] = "db"
logger.destination.debug[] = "db"
logger.destination.error[] = "file"
logger.destination.error[] = "db"
logger.destination.exception[] = "file"
logger.destination.exception[] = "db"
logger.destination.exception[] = "email"
logger.destination.fatal[] = "file"
logger.destination.fatal[] = "db"
logger.destination.fatal[] = "email"
logger.files.default.path = APPLICATION_PATH "/../logs/"
logger.files.default.name = "debug.log"
logger.files.default.mode = "a"
logger.files.default.details = false
logger.files.debug.path = APPLICATION_PATH "/../logs/"
logger.files.debug.name = "debug.log"
logger.files.debug.mode = "a"
logger.files.debug.details = false
logger.files.error.path = APPLICATION_PATH "/../logs/"
logger.files.error.name = "error.log"
logger.files.error.mode = "a"
logger.files.error.details = false
logger.files.exception.path = APPLICATION_PATH "/../logs/"
logger.files.exception.name = "exception.log"
logger.files.exception.mode = "a"
logger.files.exception.details = true
logger.files.fatal.path = APPLICATION_PATH "/../logs/"
logger.files.fatal.name = "exception.log"
logger.files.fatal.mode = "a"
logger.files.fatal.details = true
logger.restore.error = false
logger.restore.exception = false
logger.restore.display_errors = true
logger.restore.error_reporting = true
```

Please feel free to contact me with any enquiries; http://chrisdlangton.com
My site contains great tech articles: http://codewiz.biz
