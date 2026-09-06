import { useEffect, useState } from 'react'

type Health = {
    status: string
    database: string
}

export default function App() {
    const [health, setHealth] = useState<Health | null>(null)
    const [error, setError] = useState<string | null>(null)

    useEffect(() => {
        fetch('/api/health')
            .then((response) => response.json())
            .then((data: Health) => setHealth(data))
            .catch(() => setError('não foi possível falar com a API'))
    }, [])

    return (
        <main>
            <h1>Vendeu, Ganhou</h1>
            {error && <p className="error">{error}</p>}
            {health && (
                <p>
                    API: {health.status} / banco: {health.database}
                </p>
            )}
        </main>
    )
}
