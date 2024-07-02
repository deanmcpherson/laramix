
import { router } from '@inertiajs/react';
import axios from 'axios';
import React, {createContext, useState, useEffect, useContext} from 'react';

export const LaramixContext = createContext<{components: any, eager: true|undefined, depth: number}>({
    components: {},
    depth: 0
});

function makeAction(component: string, action: string, isInertia: boolean) {

    if (!isInertia) {
        return (args: any) => axios.post(`/_laramix/${component}/${action}`, {_args: args});
    }
    return (args: any) => router.post(`/_laramix/${component}/${action}`, {_args: args});
}

function transformActions(laramix: any) {
    const actions :{[key: string]: (args: any) => any} = {};
    laramix.actions.forEach((action: string) => {
        const isInertia = action.startsWith('$');
        if (isInertia) {
            action = action.slice(1);
            }

        actions[action] = makeAction(laramix.component, action, isInertia);
        })

    return actions;

}

// @ts-expect-error
const getRoutes = () => Laramix.routes;

// @ts-expect-error
const getManifest = () => Laramix.manifest;

export function resolveComponent(component: string) {
    //@ts-ignore
    const pages = getRoutes();
    const page = pages[`./routes/${component}.tsx`];

    return page();
}

const resolved: {[key: string]: any} = {};

export function useComponents(components: {component: string, props: any, actions: string[]}[]) : {[key: string]: any} {
    const [state, setState] = useState(resolved)

    useEffect(() => {
        components.forEach(async ({component}: {component: string}) => {
            if (resolved[component]) {
                setState((prev) => {
                    return {
                        ...prev,
                        [component]: resolved[component]
                    };
                });
                return;
            }
            const ready = await resolveComponent(component).then((module: any) => module.default || module);
            resolved[component] = ready;
            setState((prev) => {
                return {
                    ...prev,
                    [component]: ready
                };
            });
        });

    }, [components]);

    return state;
}


export default function Laramix({components, eager} : {
    components: any,
    eager: true|undefined
}) {

    const ComponentModules = useComponents(components);
    const preparedComponents = components.map((component: any) => {
        return {
            ...component,
            render: ComponentModules[component.component]
        }
    });

    return (
        <LaramixContext.Provider value={{
            components: preparedComponents,
            eager: eager,
            depth: 0
            //   actions: transformActions(laramix)
        }} >
            <>{
                preparedComponents[0].render?.(
                    {
                        component: components[0].component,
                        eager,
                        props: components[0].props,
                        actions: transformActions(components[0])
                    },
                )
            }
            </>
        </LaramixContext.Provider>
    );
}




export function Outlet() {

    const context = useContext(LaramixContext);

    const {components, depth, eager} = context;
    const newDepth = depth + 1;
    const nextComponent = components[newDepth];
    return <LaramixContext.Provider value={{
        components,
        eager,
        depth: newDepth
    }}>
        <>
        {
            nextComponent?.render?.({
                component: nextComponent.component,
                eager,
                props: nextComponent.props,
                actions: transformActions(nextComponent)
            })
        }
    </>
    </LaramixContext.Provider>
}

const componentsPropCache: {[key: string]: any} = {};

const resolveInertiaPageFromPath = (path: string) => {
    const manifest = getManifest();
    const parts = path.split('/');
    const route =  manifest.routes.find(({path}: {path: string}) => {
        //path can be /about or /about/{id} or /about/{id}/edit/{blah}
        const pathParts = path.split('/');
        if (pathParts.length !== parts.length) {
            return false;
        }
        return pathParts.every((part, index) => {
            if (part.startsWith('{') && part.endsWith('}')) {
                return true;
            }
            return part === parts[index];
        });
    });
    if (route) {

        const page = {
            url: path,
            component: 'Laramix',
            props: {
                eager: true,
                components: route.components.map((name:string) => {
                    const baseComponent = manifest.components.find(({component}: any) => component === name);
                    const cachedProps =loadCachedComponentProps(baseComponent.component, path);
                    if (cachedProps) {
                        return {
                            ...baseComponent,
                            props: cachedProps
                        }
                    }
                    return baseComponent;
            }).filter(x => x)
            }
        }

        //Only eager load if we know what the props look like.
        if (page.props.components.find(c => !c.props)) {
            console.log('Not eager loading', page.props.components.find(c => !c.props))
            return null;
        }
        return page;
    }
}

const populateComponentCache = (page: {url: string, props?: {parameters?: Record<string,any>, components?: {component: string, props: any}[]}})  => {

    if (!page.props?.components) {
        return;
    }
    page.props.components.forEach(({component, props}: {component: string, props: any}) => {
        if (!componentsPropCache[component]) {
            componentsPropCache[component] = new Map<string,any>()
        }

        const routeParameters = page.props?.parameters ?? {};
        const componentRouteParameterNames = component.match(/\$[a-zA-Z0-9]+/g) ?? [];
        const parameterString = componentRouteParameterNames.map((name: string) => routeParameters[name.slice(1)] ?? '').join(',');
        componentsPropCache[component].set(parameterString, props);
    });

}

const loadCachedComponentProps = (component: string, path: string) => {
    if (!componentsPropCache[component]) {
        return null;
    }
    const pathFragments = path.split('/').slice(1);


    //Extract variables from component name
    const cacheKey = component.split('.')
        .filter(part => !part.startsWith('_'))
        .map((part: string, i: number) => {
            if (part.includes('$')) {
                return pathFragments[i];
            }
            return null;
        })
        .filter((part: string|null) => part)
        .join(',');

    return componentsPropCache[component].get(cacheKey);


}

router.on('navigate', (event: any) => {

    if (!event.detail.page || event.detail.page?.eager) {
        return;
    }
    populateComponentCache(event.detail.page);
})

export function Link({href, children, data, target, method = 'get'}: {
    href: string,
    children: any,
    data?: any,
    target?: string,
    method?: 'get' | 'post' | 'put' | 'delete'
 }) {


    return <a href={href} onClick={async (e) => {
        e.preventDefault();
        const resolved = resolveInertiaPageFromPath(href);
        if (resolved) {
            try {
                await router.setPage(resolved);
                router.reload({onSuccess: populateComponentCache});
            return;
            } catch (e) {
                console.warn(e);
            }
        }
        router[method](href, data, {
            onSuccess: populateComponentCache
        })
    }} target={target}>{children}</a>

}
