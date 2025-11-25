<?php

namespace Jqhtml\Laravel\Blade;

/**
 * Blade Precompiler for jqhtml Components
 *
 * Transforms uppercase component tags into hydration placeholders.
 *
 * Example:
 *   <User_Card $name="John" $id="123" class="mt-4" />
 *
 * Becomes:
 *   <div class="_Component_Init mt-4"
 *        data-component-init-name="User_Card"
 *        data-component-args="{!! htmlspecialchars(json_encode(['name' => 'John', 'id' => '123']), ENT_QUOTES, 'UTF-8') !!}">
 *   </div>
 *
 * The client-side jqhtml.boot() function then hydrates these into live components.
 */
class JqhtmlBladePrecompiler
{
    /**
     * Precompile Blade template to transform jqhtml component tags
     *
     * @param string $value The Blade template content
     * @return string Transformed content
     */
    public static function compile(string $value): string
    {
        // Pattern to match tags that start with uppercase letter followed by underscore
        // This targets Component_Name style naming (jqhtml convention)
        // Matches both self-closing and paired tags
        $pattern = '/<([A-Z][a-zA-Z0-9]*(?:_[a-zA-Z0-9]+)+)((?:\s+:?\$?[a-zA-Z0-9_\-:]+(?:=(?:"[^"]*"|\'[^\']*\'|[^>\s]+))?)*)\s*(?:\/>|>(.*?)<\/\1>)/s';

        $value = preg_replace_callback($pattern, function ($matches) {
            $component_name = $matches[1];
            $attributes = $matches[2] ?? '';
            $slot_content = $matches[3] ?? null;

            // Parse attributes into array
            $parsed_attrs = self::parseAttributes($attributes);

            // Separate $-prefixed attributes (component args) from regular HTML attributes
            $component_args = [];
            $html_attrs = [];

            foreach ($parsed_attrs as $key => $attr) {
                if (str_starts_with($key, '$')) {
                    // Component arg - remove $ prefix
                    $arg_key = substr($key, 1);
                    $component_args[$arg_key] = $attr;
                } elseif (str_starts_with($key, 'data-')) {
                    // data- attributes also become component args
                    $arg_key = substr($key, 5);
                    $component_args[$arg_key] = $attr;
                } else {
                    // Regular HTML attribute
                    $html_attrs[$key] = $attr;
                }
            }

            // Build component args JSON expression
            $json_args = self::toPhpArrayExpression($component_args);
            if (empty($json_args) || $json_args === '[]') {
                $args_output = '[]';
            } else {
                $args_output = "{!! htmlspecialchars(json_encode({$json_args}), ENT_QUOTES, 'UTF-8') !!}";
            }

            // Build HTML attributes string
            // Handle class attribute specially to merge with _Component_Init
            $class_value = '_Component_Init';
            if (isset($html_attrs['class'])) {
                if ($html_attrs['class']['type'] === 'expression') {
                    $class_value = "_Component_Init {{ {$html_attrs['class']['value']} }}";
                } else {
                    $class_value = '_Component_Init ' . $html_attrs['class']['value'];
                }
            }

            $attrs_string = ' class="' . $class_value . '"';

            foreach ($html_attrs as $key => $attr) {
                if ($key === 'class') {
                    continue; // Already handled above
                }

                if ($attr['type'] === 'expression') {
                    // Blade expression - output with {{ }}
                    $attrs_string .= ' ' . $key . '="{{ ' . $attr['value'] . ' }}"';
                } elseif ($attr['value'] === true) {
                    // Boolean attribute
                    $attrs_string .= ' ' . $key;
                } else {
                    // String value
                    $attrs_string .= ' ' . $key . '="' . htmlspecialchars($attr['value'], ENT_QUOTES) . '"';
                }
            }

            // Handle slot content (innerHTML)
            if ($slot_content !== null && trim($slot_content) !== '') {
                // Recursively process slot content for nested components
                $slot_content = self::compile($slot_content);

                return sprintf(
                    '<div data-component-init-name="%s" data-component-args="%s"%s>%s</div>',
                    $component_name,
                    $args_output,
                    $attrs_string,
                    $slot_content
                );
            }

            // Self-closing tag
            return sprintf(
                '<div data-component-init-name="%s" data-component-args="%s"%s></div>',
                $component_name,
                $args_output,
                $attrs_string
            );
        }, $value);

        return $value;
    }

    /**
     * Parse HTML attributes into key-value pairs
     *
     * @param string $attributes HTML attributes string
     * @return array
     */
    private static function parseAttributes(string $attributes): array
    {
        $parsed = [];

        // Match attribute patterns
        // Supports: name="value", name='value', name=value, name (boolean), :name="expr", $name="value"
        preg_match_all('/(:?\$?[a-zA-Z0-9_\-:]+)(?:=(?:"([^"]*)"|\'([^\']*)\'|([^>\s]+)))?/', $attributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];

            // Determine value
            if (isset($match[2]) && $match[2] !== '') {
                $value = $match[2]; // Double quoted
            } elseif (isset($match[3]) && $match[3] !== '') {
                $value = $match[3]; // Single quoted
            } elseif (isset($match[4]) && $match[4] !== '') {
                $value = $match[4]; // Unquoted
            } else {
                $value = true; // Boolean attribute
            }

            // Handle : prefix (Blade expression binding)
            if (str_starts_with($key, ':')) {
                $key = substr($key, 1);
                $parsed[$key] = ['type' => 'expression', 'value' => $value];
            } elseif (is_string($value) && preg_match('/^\{\{\s*(.+?)\s*\}\}$/', $value, $blade_match)) {
                // Value contains {{ expression }}
                $parsed[$key] = ['type' => 'expression', 'value' => $blade_match[1]];
            } elseif (is_string($value) && preg_match('/^\{!!\s*(.+?)\s*!!\}$/', $value, $blade_match)) {
                // Value contains {!! expression !!}
                $parsed[$key] = ['type' => 'expression', 'value' => $blade_match[1]];
            } else {
                // Regular string value
                $parsed[$key] = ['type' => 'string', 'value' => $value];
            }
        }

        return $parsed;
    }

    /**
     * Convert parsed attributes to PHP array expression
     *
     * @param array $attrs
     * @return string
     */
    private static function toPhpArrayExpression(array $attrs): string
    {
        if (empty($attrs)) {
            return '[]';
        }

        $parts = [];
        foreach ($attrs as $key => $attr) {
            $escaped_key = addslashes($key);

            if ($attr['type'] === 'expression') {
                // PHP expression - use as-is
                $parts[] = "'{$escaped_key}' => {$attr['value']}";
            } elseif ($attr['value'] === true) {
                // Boolean true
                $parts[] = "'{$escaped_key}' => true";
            } else {
                // String value - escape and quote
                $escaped_value = addslashes($attr['value']);
                $parts[] = "'{$escaped_key}' => '{$escaped_value}'";
            }
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
