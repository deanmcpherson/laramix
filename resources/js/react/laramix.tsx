import { router } from "@inertiajs/react";
import axios from "axios";

import React, { createContext, useState, useEffect, useContext, ElementType, useMemo } from "react";

const LaramixContext = createContext<{
    components: ResolvedComponent[];
    actions: {
        [key: string]: {
            [key:string]: (args: any, options: any) => any;
        };
    }
    eager: true | undefined;
    errors: any;
    depth: number;
    routes: LaramixProps['routes'];
    manifest: LaramixProps['manifest'];
    populateComponentCache: (page: any) => void;
    resolveInertiaPageFromPath: (path: string) => any;
}>({
    components: {},
    depth: 0,
    eager: undefined,
    routes: {},
    manifest: {},
    populateComponentCache: () => {},
    resolveInertiaPageFromPath: () => {},
});

function makeAction(component: string, action: string, tanstack?: any) {
    return {
        use: (mutationOptions: any, options: any) => tanstack?.useMutation({
            mutationFn: (args: any) => axios.put(window.location.href, { _args: args, _component: component, _action: action }, options).then((res) => res.data),
            ...mutationOptions
        }),
        call: (args: any, options: any) => axios.put(window.location.href, { _args: args, _component: component, _action: action }, options),
        visit: (args: any, options: any) => router.put(window.location.href, { _args: args, _component: component, _action: action },  options),
    }

}

function transformActions(component: ResolvedComponent, tanstack: any) {
    const actions: { [key: string]: (args: any) => any } = {};
    component.actions.forEach((action: string) => {
        actions[action] = makeAction(component.component, action, tanstack);
    });

    return actions;
}



interface Component {
    component: string;
    props: any;
    actions: string[];

}


interface ResolvedComponent extends Component {
    render?: (props: any) => any;
}
interface Route {
    path: string;
    components: string[];
}

interface LaramixProps {
    routes: {
        [key: string]: () => any;
    };
    manifest: {
        components: Component[];
        routes: Route[];
    };
    tanstack? : {useMutation: any, QueryClient: any, QueryClientProvider: any}
}


const Laramix = ({ routes, manifest, tanstack }: LaramixProps) => {

    const getRoutes = () => routes;
    const getManifest = () => manifest;

    const actions = manifest.components.reduce((result, component: any) => {
        if (component.actions) {
            result[component.component] = transformActions(component, tanstack);
        }
        return result;
    }, {});


    async function resolveComponent(component: string) {
    
        const pages = getRoutes();
        
        let page = pages[`./routes/${component}.tsx`];
        if (!page) {
            page = pages[`./routes/${component}.mix`];
        }
        if (!page) {
            page = pages[`./routes/${component}.php`];
        }
        if (typeof page === 'function') {
            return page();
        }
        return page;
    }

    const componentsPropCache: { [key: string]: any } = {};

    const checkPathFromManifest = (path: string) => {
        const manifest = getManifest();
        const parts = path.split("/");
        return manifest.routes.find(({ path }: { path: string }) => {
            //path can be /about or /about/{id} or /about/{id}/edit/{blah}
            const pathParts = path.split("/");

            if (pathParts.length !== parts.length) {
                return false;
            }
            return pathParts.every((part, index) => {
                if (part.startsWith("{") && part.endsWith("}")) {
                    return true;
                }
                return part === parts[index];
            });
        });
    };

    const resolveInertiaPageFromPath = (path: string) => {
        const manifest = getManifest();
        let route: any = null;
        if (!path.endsWith("/")) {
            route = checkPathFromManifest(path + "/");
        }

        if (!route) {
            route = checkPathFromManifest(path);
        }

        if (route) {
            //Check if route has an _index page.
            const page = {
                url: path,
                component: "Laramix",
                props: {
                    eager: true,
                    components: route.components
                        .map((name: string) => {
                            const baseComponent = manifest.components.find(
                                ({ component }: any) => component === name
                            );
                            if (!baseComponent) {
                                return null;
                            }
                            const cachedProps = loadCachedComponentProps(
                                baseComponent.component,
                                path
                            );
                            if (cachedProps) {
                                return {
                                    ...baseComponent,
                                    props: cachedProps,
                                };
                            }
                            return baseComponent;
                        })
                        .filter((x: any) => x),
                },
            };

            //Only eager load if we know what the props look like.
            if (page.props.components.find((c) => !c.props)) {
                console.log(
                    "Not eager loading",
                    page.props.components.find((c) => !c.props)
                );
                return null;
            }
            return page;
        }
    };

    const populateComponentCache = (page: {
        url: string;
        props?: {
            parameters?: Record<string, any>;
            components?: { component: string; props: any }[];
        };
    }) => {
        if (!page.props?.components) {
            return;
        }
        page.props.components.forEach(
            ({ component, props }: { component: string; props: any }) => {
                if (!componentsPropCache[component]) {
                    componentsPropCache[component] = new Map<string, any>();
                }

                const routeParameters = page.props?.parameters ?? {};
                const componentRouteParameterNames =
                    component.match(/\$[a-zA-Z0-9]+/g) ?? [];
                const parameterString = componentRouteParameterNames
                    .map((name: string) => routeParameters[name.slice(1)] ?? "")
                    .join(",");
                componentsPropCache[component].set(parameterString, props);
            }
        );
    };

    const loadCachedComponentProps = (component: string, path: string) => {
        if (!componentsPropCache[component]) {
            return null;
        }
        const pathFragments = path.split("/").slice(1);

        //Extract variables from component name
        const cacheKey = component
            .split(".")
            .filter((part) => !part.startsWith("_"))
            .map((part: string, i: number) => {
                if (part.includes("$")) {
                    return pathFragments[i];
                }
                return null;
            })
            .filter((part: string | null) => part)
            .join(",");

        return componentsPropCache[component].get(cacheKey);
    };

    router.on("navigate", (event: any) => {
        if (!event.detail.page || event.detail.page?.eager) {
            return;
        }
        populateComponentCache(event.detail.page);
    });

    const resolved: { [key: string]: any } = {};

    function useComponents(
        components: Component[]
    ): { [key: string]: ResolvedComponent } {
        const [state, setState] = useState(resolved);

        useEffect(() => {
            components.forEach(async ({ component }: { component: string }) => {
                if (resolved[component]) {
                    setState((prev) => {
                        return {
                            ...prev,
                            [component]: resolved[component],
                        };
                    });
                    return;
                }
                const ready = await resolveComponent(component).then(
                    (module: any) => module.default || module
                );
                resolved[component] = ready;
                setState((prev) => {
                    return {
                        ...prev,
                        [component]: ready,
                    };
                });
            });
        }, [components]);

        return state;
    }

    function LaramixContainer({
        components,
        eager,
        errors,
        parameters,
        late
    }: {
        components: Component[];
        eager: true | undefined;
        errors: any;
        parameters: any;
        late: any
    }) {

       
        const ComponentModules = useComponents(components);

        const preparedComponents : ResolvedComponent[]= components.map((component, i) => {

            if (late?.components?.[i]) {
                for (const key in late.components[i]) {
                    component.props[key] = late.components[i][key];
                }
            }

            return {
                ...component,
                index: i,
                render: ComponentModules[component.component],
            };
        });

        const QueryClientProvider = tanstack?.QueryClientProvider ?? React.Fragment;
        const queryClient = useMemo(() => tanstack ? new tanstack.QueryClient() : null, []);
        
        return (
            <QueryClientProvider client={queryClient}>
            <LaramixContext.Provider
                value={{
                    actions,
                    populateComponentCache,
                    resolveInertiaPageFromPath,
                    routes,
                    manifest,
                    errors,
                    components: preparedComponents,
                    eager: eager,
                    depth: -1,
                }}
            >
                <Outlet />
            </LaramixContext.Provider>
            </QueryClientProvider>
        );
    }
    return LaramixContainer;
};
export default Laramix;

export function useActions<T>() {
    const context = useContext(LaramixContext);
    const { actions } = context;
    return actions as T;
}

function transformKeysToAdditional(keys: string[], index: number) {
    return keys.map((key) => {
        return `late.components.${index}.${key}`
    })
}



function makeRouter(component: any) {
    const r: router = {};
    Object.setPrototypeOf(r, router);
    r.reload = (options: any) => {
    
        const prepared: any = {
            
        }

        if (options.only) {
            prepared.only = transformKeysToAdditional(options.only, component.index)
        }
        if (options.except) {
            prepared.except = transformKeysToAdditional(options.except, component.index)
        }
        router.reload(prepared)
    }
    return r;
}

export function Outlet() {
    const context = useContext(LaramixContext);
    const { components, depth, eager } = context;
    const newDepth = depth + 1;
    const nextComponent = components[newDepth];
    const Component = nextComponent?.render as ElementType;
    const localRouter = useMemo(() => nextComponent ? makeRouter(nextComponent) : null, [nextComponent?.index]);
    if (!nextComponent) {
        return null;
    }
    return (
        <LaramixContext.Provider
            value={{
                ...context,
                depth: newDepth,
            }}
        >
            <>
                {Component && (
                <Component
                component={nextComponent.component}
                eager={eager}
                router={
                    localRouter
                }
                key={newDepth + nextComponent?.component}
                props={nextComponent.props}
                errors={context.errors}
                parameters={context.parameters}
                actions={context.actions[nextComponent.component]}
                />)}


            </>
        </LaramixContext.Provider>
    );
}
export function Link({
    href,
    children,
    data,
    target,
    method = "get",
}: {
    href: string;
    children: any;
    data?: any;
    target?: string;
    method?: "get" | "post" | "put" | "delete";
}) {
    const context = useContext(LaramixContext);
    return (
        <a
            href={href}
            onClick={async (e) => {
                e.preventDefault();
                const resolved = context.resolveInertiaPageFromPath(href);
                if (resolved) {
                    try {

                        await router.setPage(resolved);
                        router.reload({ onSuccess: context.populateComponentCache });
                        return;
                    } catch (e) {
                        console.warn(e);
                    }
                }
                router[method](href, data, {
                    onSuccess: context.populateComponentCache,
                });
            }}
            target={target}
        >
            {children}
        </a>
    );
}
