export default function laramixVite() {

    return {
        name: "laramix-vite-plugin",
        enforce: "pre",
        config(config) {
            return {
                ...config,
                build: {
                    ...config.build,
                    rollupOptions: {
                        ...config.build.rollupOptions
                    },
                },
                esbuild: {
                    ...config.esbuild,
                    include: /\.(js|ts|jsx|tsx|php|mix)$/, // .myext
                    loader: "tsx",
                }
            };
        },

        async transform(src, id) {
            if (id.endsWith(".tsx")) {
                //Strip everything inside php tags
                return src.replace(/<\?php([\s\S]*?)\?>/g, "");
            }
        },
    };
}
