# jqhtml/laravel

Laravel Blade integration for [jqhtml](https://jqhtml.org/).

This package provides a Blade precompiler that lets you use jqhtml component syntax directly in your templates.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Node.js 18+

## Installation

```bash
composer require jqhtml/laravel:dev-main
php artisan jqhtml:install
npm install
```

The package is currently in development and requires the `dev-main` branch.

The install command automatically:
- Adds npm dependencies to package.json
- Configures the Vite plugin
- Sets up app.js with jqhtml boot code
- Creates the `resources/jqhtml/` directory

## Usage

### Using Components in Blade

Write components directly in your Blade templates:

```blade
<AlertBox $title="Notice" $message="This is a jqhtml component." />
```

Pass PHP variables with the `:` prefix:

```blade
<UserCard :$name="$user->name" :$email="$user->email" />
```

Components can contain content:

```blade
<Card $title="Welcome">
    <p>This content is available via content() in the template.</p>
</Card>
```

### Registering Components

Create `.jqhtml` files in `resources/jqhtml/`, then register them in `resources/js/app.js`:

```javascript
// Template-only components
import AlertBox from '../jqhtml/AlertBox.jqhtml';
import UserCard from '../jqhtml/UserCard.jqhtml';

// Interactive components with JS class
import Counter from '../jqhtml/Counter.js';

jqhtml.register(AlertBox);
jqhtml.register(UserCard);
jqhtml.register(Counter);
```

### Interactive Components

For components with behavior, create a JS class alongside the template:

```html
<!-- resources/jqhtml/Counter.jqhtml -->
<Define:Counter tag="div" class="counter">
    <button $sid="decrement">-</button>
    <span $sid="display"><%= this.data.count %></span>
    <button $sid="increment">+</button>
</Define:Counter>
```

```javascript
// resources/jqhtml/Counter.js
import { Jqhtml_Component } from '@jqhtml/core';
import CounterTemplate from './Counter.jqhtml';

class Counter extends Jqhtml_Component {
    on_create() {
        this.data.count = this.args.initial || 0;
    }

    on_ready() {
        this.$sid('increment').on('click', () => {
            this.data.count++;
            this.$sid('display').text(this.data.count);
        });
        this.$sid('decrement').on('click', () => {
            this.data.count--;
            this.$sid('display').text(this.data.count);
        });
    }
}

export default Counter;
```

#### Note on Minification

If your build uses class name mangling, you must either:

1. Add `static component_name = 'Counter'` to each component class, or
2. Use explicit registration:

```javascript
jqhtml.register_template(CounterTemplate);
jqhtml.register_component('Counter', Counter);
```

### How It Works

The Blade precompiler transforms component tags into hydration placeholders:

```
Blade:  <AlertBox $title="Hi" />
  ↓
HTML:   <div class="_Component_Init" data-component-init-name="AlertBox" data-component-args='{"title":"Hi"}'></div>
  ↓
Live:   <div class="AlertBox alert">...</div>
```

The client-side `boot()` function finds these placeholders and hydrates them into live components.

## Manual Installation

If you prefer manual setup instead of `php artisan jqhtml:install`:

1. Install npm packages:
   ```bash
   npm install @jqhtml/core @jqhtml/vite-plugin jquery
   ```

2. Add the plugin to `vite.config.js`:
   ```javascript
   import jqhtml from '@jqhtml/vite-plugin';

   export default defineConfig({
       plugins: [
           jqhtml(),
           laravel({ /* ... */ }),
       ],
   });
   ```

3. Set up `resources/js/app.js`:
   ```javascript
   import $ from 'jquery';
   window.jQuery = window.$ = $;

   import jqhtml, { boot, init_jquery_plugin } from '@jqhtml/core';
   init_jquery_plugin($);

   // Register your components here

   $(document).ready(async () => {
       await boot();
   });
   ```

## Documentation

For complete documentation including template syntax, lifecycle methods, and component patterns:

**https://jqhtml.org/**

## License

MIT - Copyright (c) hansonxyz
