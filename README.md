# wunderupdates-plugin-class

The WunderUpdates PHP class for plugins. Visit [wunderupdates.com](https://wunderupdates.com) to learn more.

## Instructions

To integrate your plugin with WunderUpdates, you'll need to add the WunderUpdates PHP class to your plugin. This class
is responsible for handling the communication between your plugin and the WunderUpdates API so that your plugin can
check for updates, download new versions, and verify the license key.

### Step 1: Download the class

Download the latest version of `class-wp-updates.php` from the [GitHub
repository](https://github.com/Wundermatics/wunderupdates-plugin-class).

### Step 2: Copy the class to your plugin

Copy the `class-wp-updates.php` file to your plugin directory. You can rename it or place it in the root of your plugin
or in a subdirectory, whatever works best for you.

### Step 3: Set a new class name

To avoid conflicts with other plugins that may use the same class name, you should rename the class to something unique.
We suggest naming the class to match your WunderUpdates account key and plugin slug, for example:

```php
class WunderUpdates_abcde123_hello_world {
    // ...rest of the class here.
}
``` 
### Step 4: Configure the class

To configure the class in your plugin, you need to include it and set some properties

```php
require_once __DIR__ . '/class-wp-updates.php';
$updates = new WunderUpdates_abcde123_hello_world( array(
		'version'     => '1.0.0',      // The current version of the plugin.
		'slug'        => 'hello-nick', // Plugin slug.
		'full_path'   => __FILE__,     // Full path to the root plugin file.
		'account_key' => 'DxirA2y6',   // Your WunderUpdates account key.
	) );
```

