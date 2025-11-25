import type { Plugin, HmrContext } from 'vite';
import { compileTemplate } from '@jqhtml/parser';
import { createFilter, FilterPattern } from 'vite';

export interface JqhtmlPluginOptions {
    /**
     * Glob patterns to include
     * @default ['**\/*.jqhtml']
     */
    include?: FilterPattern;

    /**
     * Glob patterns to exclude
     * @default ['node_modules/**']
     */
    exclude?: FilterPattern;

    /**
     * Enable source maps
     * @default true in development
     */
    sourcemap?: boolean;
}

export default function jqhtmlPlugin(options: JqhtmlPluginOptions = {}): Plugin {
    const {
        include = ['**/*.jqhtml'],
        exclude = ['node_modules/**'],
        sourcemap,
    } = options;

    const filter = createFilter(include, exclude);
    let isDev = false;

    return {
        name: 'vite-plugin-jqhtml',

        configResolved(config) {
            isDev = config.command === 'serve';
        },

        transform(code: string, id: string) {
            if (!id.endsWith('.jqhtml')) return null;
            if (!filter(id)) return null;

            try {
                // Compile the template using @jqhtml/parser
                // API: compileTemplate(source, filename, options)
                const compiled = compileTemplate(code, id, {
                    format: 'esm', // ES module output
                    sourcemap: sourcemap ?? isDev,
                });

                // The compiled output includes the component name derived from filename
                // We wrap it to also export the component name for registration
                const output = `
${compiled.code}

// Export component name for manual registration
export const __jqhtml_component_name = ${JSON.stringify(compiled.componentName)};
`;

                return {
                    code: output,
                    map: null, // Sourcemap is embedded inline when enabled
                };
            } catch (error: any) {
                // Format error for Vite's error overlay
                let message = error.message || 'jqhtml compilation failed';

                // Append context and suggestion from parser if available
                if (error.context) {
                    message += `\n\nContext: ${error.context}`;
                }
                if (error.suggestion) {
                    message += `\nSuggestion: ${error.suggestion}`;
                }

                const line = error.line || 1;
                const column = error.column || 0;

                this.error({
                    message: `jqhtml compilation error: ${message}`,
                    id,
                    loc: { line, column },
                });
            }
        },

        handleHotUpdate(ctx: HmrContext) {
            if (ctx.file.endsWith('.jqhtml')) {
                // Invalidate the module to trigger re-compilation
                const module = ctx.server.moduleGraph.getModuleById(ctx.file);
                if (module) {
                    ctx.server.moduleGraph.invalidateModule(module);
                    return [module];
                }
            }
        },
    };
}

// Also export as named export
export { jqhtmlPlugin };
