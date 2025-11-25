# jqhtml-laravel

Laravel integration for [jqhtml](https://jqhtml.org/) - a jQuery-based component system.

This package enables jqhtml components in Laravel Blade templates with automatic hydration.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Node.js 18+
- jQuery 3.7+

## Installation

### 1. Install PHP Package

```bash
composer require jqhtml/laravel
```

### 2. Install npm Packages

```bash
npm install @jqhtml/core @jqhtml/vite-plugin jquery
```

### 3. Configure Vite

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import jqhtml from '@jqhtml/vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        jqhtml(),
    ],
});
```

### 4. Set Up app.js

```javascript
// resources/js/app.js
import $ from 'jquery';
window.jQuery = window.$ = $;

import jqhtml, { init_jquery_plugin } from '@jqhtml/core';
init_jquery_plugin($);

// Import your jqhtml templates
import '../jqhtml/MyComponent.jqhtml';

// Hydrate components on page load
$(document).ready(() => {
    jqhtml.boot();
});
```

## Usage

### Creating Components

Create `.jqhtml` template files in `resources/jqhtml/`:

```html
<!-- resources/jqhtml/AlertBox.jqhtml -->
<Define:AlertBox tag="div" class="alert">
    <strong><%= this.args.title %></strong>
    <p><%= this.args.message %></p>
</Define:AlertBox>
```

Import templates in your `app.js`:

```javascript
import '../jqhtml/AlertBox.jqhtml';
```

### Using Components in Blade

Use jqhtml component syntax directly in Blade templates:

```blade
<AlertBox $title="Notice" $message="This is a jqhtml component." />
```

Pass PHP variables with the `:` prefix:

```blade
@php $user = Auth::user(); @endphp
<UserCard :$name="$user->name" :$email="$user->email" />
```

Components can contain content:

```blade
<CardComponent $title="Welcome">
    <p>This content is available via content() in the template.</p>
</CardComponent>
```

### Components with JavaScript Classes

For components needing lifecycle methods or state, create a companion JS class:

```javascript
// resources/js/components/DataTable.js
import { JqhtmlComponent } from '@jqhtml/core';

export class DataTable extends JqhtmlComponent {
    on_create() {
        this.data.rows = [];
    }

    async on_load() {
        this.data.rows = await fetch(this.args.source).then(r => r.json());
    }
}
```

The `JqhtmlComponent` export can be named any variable in your code; `JqhtmlComponent` is the standard convention.

Register the class in `app.js`:

```javascript
import jqhtml from '@jqhtml/core';
import { DataTable } from './components/DataTable.js';

jqhtml.register_component('DataTable', DataTable);
```

### Hydration

The `jqhtml.boot()` function finds all component placeholders in the DOM and hydrates them into live components:

```javascript
// Basic usage
jqhtml.boot();

// With promise
jqhtml.boot().then(() => {
    console.log('All components ready');
});

// Hydrate specific container
jqhtml.boot(document.getElementById('my-section'));
```

Listen for the ready event:

```javascript
document.addEventListener('jqhtml:ready', () => {
    // All components have completed their lifecycle
});
```

## How It Works

1. Blade precompiler transforms `<ComponentName />` tags into placeholder divs
2. Server sends HTML with `_Component_Init` placeholders
3. Client-side `jqhtml.boot()` finds placeholders and hydrates them into live components

```
Blade:  <AlertBox $title="Hi" />
  ↓
HTML:   <div class="_Component_Init" data-component-init-name="AlertBox" data-component-args='{"title":"Hi"}'></div>
  ↓
Live:   <div class="AlertBox alert">...</div>
```

## Documentation

For complete jqhtml documentation including template syntax, lifecycle methods, and component patterns, visit:

**https://jqhtml.org/**

## License

MIT License - Copyright (c) hansonxyz
