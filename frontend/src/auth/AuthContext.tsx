import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { api, setUnauthorizedHandler } from '../api/client'
import { clearSession, readSession, writeSession } from './session'
import type { Session } from './session'

type AuthContextValue = {
    session: Session | null
    login: (email: string, password: string) => Promise<void>
    logout: () => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
    const [session, setSession] = useState<Session | null>(() => readSession())

    const logout = useCallback(() => {
        clearSession()
        setSession(null)
    }, [])

    useEffect(() => {
        setUnauthorizedHandler(() => setSession(null))

        return () => setUnauthorizedHandler(() => {})
    }, [])

    const login = useCallback(async (email: string, password: string) => {
        const response = await api.login(email, password)
        const next: Session = { token: response.token, user: response.user }

        writeSession(next)
        setSession(next)
    }, [])

    const value = useMemo<AuthContextValue>(() => ({ session, login, logout }), [session, login, logout])

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
    const value = useContext(AuthContext)

    if (value === null) {
        throw new Error('useAuth precisa estar dentro de AuthProvider')
    }

    return value
}
