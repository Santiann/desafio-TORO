import { useEffect, useState } from 'react'

export function readRoute(): string {
    const hash = window.location.hash

    return hash.startsWith('#') ? hash.slice(1) : '/'
}

export function navigate(path: string): void {
    if (readRoute() !== path) {
        window.location.hash = path
    }
}

export function useRoute(): string {
    const [route, setRoute] = useState(readRoute)

    useEffect(() => {
        const sync = () => setRoute(readRoute())

        window.addEventListener('hashchange', sync)

        return () => window.removeEventListener('hashchange', sync)
    }, [])

    return route
}
