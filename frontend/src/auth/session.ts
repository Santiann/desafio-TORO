import type { AuthUser } from '../api/types'

const STORAGE_KEY = 'vendeu-ganhou.session'

export type Session = {
    token: string
    user: AuthUser
}

export function readSession(): Session | null {
    const raw = window.localStorage.getItem(STORAGE_KEY)

    if (raw === null) {
        return null
    }

    try {
        const parsed: unknown = JSON.parse(raw)

        return isSession(parsed) ? parsed : null
    } catch {
        return null
    }
}

export function writeSession(session: Session): void {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(session))
}

export function clearSession(): void {
    window.localStorage.removeItem(STORAGE_KEY)
}

function isSession(value: unknown): value is Session {
    if (typeof value !== 'object' || value === null) {
        return false
    }

    const candidate = value as Record<string, unknown>
    const user = candidate.user

    if (typeof candidate.token !== 'string' || typeof user !== 'object' || user === null) {
        return false
    }

    const fields = user as Record<string, unknown>

    return typeof fields.id === 'number'
        && typeof fields.name === 'string'
        && typeof fields.email === 'string'
        && (fields.role === 'admin' || fields.role === 'seller')
}
