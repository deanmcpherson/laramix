
import { router } from '@inertiajs/react';
import axios from 'axios';
import React, {createContext, useState, useEffect, useContext} from 'react';

export const LaramixContext = createContext({});

function makeAction(component: string, action: string, isInertia: boolean) {

    if (!isInertia) {
        return (args: any) => axios.post(`/laramix/${component}/${action}`, {_args: args});
    }
    return (args: any) => router.post(`/laramix/${component}/${action}`, {_args: args});
}

function transformActions(laramix: any) {
    const actions = {};
    laramix.actions.forEach((action: string) => {
        const isInertia = action.startsWith('$');
        if (isInertia) {
            action = action.slice(1);
            }

        actions[action] = makeAction(laramix.component, action, isInertia);
        })

    return actions;

}

export function resolveComponent(component: string) {
    //@ts-ignore
    const pages = LaramixProvider.routes;
    const page = pages[`./routes/${component}.tsx`];
    return page;
}


export function useComponents(components) {


    const [state, setState] = useState({})

    useEffect(() => {
        components.forEach(async ({component}) => {
            const ready = await Promise.resolve(resolveComponent(component)).then((module) => module.default || module);
            setState((prev) => {
                prev[component] = ready;
                return prev;
            });
        });

    }, [components]);

    return state;
}


export default function LaramixProvider({components} : {
    components: any
}) {

    const ComponentModules = useComponents(components);
    const preparedComponents = components.map((component) => {
        return {
            ...component,
            render: ComponentModules[component.component]
        }
    });

    return (
        <LaramixContext.Provider value={{
            components: preparedComponents,
            depth: 0
            //   actions: transformActions(laramix)
        }} >
            <>{
                preparedComponents[0].render?.(
                    {
                        component: components[0].component,
                        props: components[0].props,
                        actions: transformActions(components[0])
                    },
                )
            }
            </>

        </LaramixContext.Provider>
    );
}


export function useRoute<T>(): T {
    const context = React.useContext(LaramixContext);
    return context;
}



export function Outlet() {

    const context = useContext(LaramixContext);

    const {components, depth} = context;
    const newDepth = depth + 1;
    const nextComponent = components[newDepth];
    return <LaramixContext.Provider value={{
        components,
        depth: newDepth
    }}>
        <>
        {
            nextComponent?.render?.({
                component: nextComponent.component,
                props: nextComponent.props,
                actions: transformActions(nextComponent)
            })
        }
    </>
    </LaramixContext.Provider>
}
