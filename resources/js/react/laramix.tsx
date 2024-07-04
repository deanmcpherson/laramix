import { router } from "@inertiajs/react";
import axios from "axios";
import React, { createContext, useState, useEffect, useContext, ElementType } from "react";

const LaramixContext = createContext<{
    components: ResolvedComponent[];
    actions: {
        [key: string]: {
            [key:string]: (args: any, options: any) => any;
        };
    }
    eager: true | undefined;
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

function makeAction(component: string, action: string, isInertia: boolean) {
    if (!isInertia) {
        return (args: any, options: any) =>
            axios.put(`/_laramix/${component}/${action}`, { _args: args }, options);
    }
    return (args: any, options: any) =>
        router.put(`/_laramix/${component}/${action}`, { _args: args },  options);
}

function transformActions(component: ResolvedComponent) {
    const actions: { [key: string]: (args: any) => any } = {};
    component.actions.forEach((action: string) => {
        const isInertia = action.startsWith("$");
        if (isInertia) {
            action = action.slice(1);
        }

        actions[action] = makeAction(component.component, action, isInertia);
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
}


const Laramix = ({ routes, manifest }: LaramixProps) => {

    const getRoutes = () => routes;
    const getManifest = () => manifest;

    const actions = manifest.components.reduce((result, component: any) => {
        if (component.actions) {
            result[component.component] = transformActions(component);
        }
        return result;
    }, {});


    async function resolveComponent(component: string) {
        const pages = getRoutes();
        const page = pages[`./routes/${component}.tsx`];
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
    }: {
        components: Component[];
        eager: true | undefined;
    }) {
        const ComponentModules = useComponents(components);

        const preparedComponents : ResolvedComponent[]= components.map((component) => {
            return {
                ...component,
                render: ComponentModules[component.component],
            };
        });

        return (
            <LaramixContext.Provider
                value={{
                    actions,
                    populateComponentCache,
                    resolveInertiaPageFromPath,
                    routes,
                    manifest,
                    components: preparedComponents,
                    eager: eager,
                    depth: 0,
                }}
            >
                <>
                    {preparedComponents[0].render?.({
                        component: components[0].component,
                        eager,
                        props: components[0].props,
                        actions: actions[components[0].component],
                    })}
                </>
            </LaramixContext.Provider>
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

export function Outlet() {
    const context = useContext(LaramixContext);
    const { components, depth, eager } = context;
    const newDepth = depth + 1;
    const nextComponent = components[newDepth];
    const Component = nextComponent?.render as ElementType;
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
                key={newDepth + nextComponent?.component}
                props={nextComponent.props}
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
