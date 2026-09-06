import { useState } from 'react'
import type { FormEvent } from 'react'
import { useAuth } from '../auth/AuthContext'
import { ErrorBox } from '../components/Feedback'

export default function LoginPage() {
    const { login } = useAuth()
    const [email, setEmail] = useState('')
    const [password, setPassword] = useState('')
    const [error, setError] = useState<unknown>(null)
    const [pending, setPending] = useState(false)

    async function submit(event: FormEvent) {
        event.preventDefault()
        setError(null)
        setPending(true)

        try {
            await login(email, password)
        } catch (failure) {
            setError(failure)
        } finally {
            setPending(false)
        }
    }

    return (
        <main className="login">
            <h1>Vendeu, Ganhou</h1>
            <form onSubmit={submit}>
                <label htmlFor="email">E-mail</label>
                <input
                    id="email"
                    type="email"
                    autoComplete="username"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    required
                />

                <label htmlFor="password">Senha</label>
                <input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    required
                />

                <button type="submit" disabled={pending}>
                    {pending ? 'entrando...' : 'entrar'}
                </button>
            </form>

            <ErrorBox error={error} />
        </main>
    )
}
