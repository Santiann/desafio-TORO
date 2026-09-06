import { useEffect } from 'react'
import type { ReactElement } from 'react'
import { AuthProvider, useAuth } from './auth/AuthContext'
import type { Role } from './api/types'
import CampaignsPage from './pages/CampaignsPage'
import LoginPage from './pages/LoginPage'
import ProductsPage from './pages/ProductsPage'
import SalesPage from './pages/SalesPage'
import WalletPage from './pages/WalletPage'
import { navigate, useRoute } from './routing'

type Entry = {
    path: string
    label: string
    render: () => ReactElement
}

const MENU: Record<Role, Entry[]> = {
    admin: [
        { path: '/products', label: 'produtos', render: () => <ProductsPage /> },
        { path: '/campaigns', label: 'campanhas', render: () => <CampaignsPage /> },
        { path: '/sales', label: 'vendas', render: () => <SalesPage /> },
    ],
    seller: [{ path: '/wallet', label: 'carteira', render: () => <WalletPage /> }],
}

export default function App() {
    return (
        <AuthProvider>
            <Shell />
        </AuthProvider>
    )
}

function Shell() {
    const { session, logout } = useAuth()
    const route = useRoute()
    const entries = session === null ? [] : MENU[session.user.role]
    const active = entries.find((entry) => entry.path === route) ?? entries[0]

    useEffect(() => {
        navigate(active?.path ?? '/login')
    }, [active])

    if (session === null || active === undefined) {
        return <LoginPage />
    }

    return (
        <div className="app">
            <header>
                <h1>Vendeu, Ganhou</h1>
                <nav>
                    {entries.map((entry) => (
                        <a
                            key={entry.path}
                            href={`#${entry.path}`}
                            className={entry.path === active.path ? 'current' : undefined}
                        >
                            {entry.label}
                        </a>
                    ))}
                </nav>
                <div className="identity">
                    <span>
                        {session.user.name} ({session.user.role === 'admin' ? 'admin' : 'vendedor'})
                    </span>
                    <button type="button" onClick={logout}>
                        sair
                    </button>
                </div>
            </header>

            <main>{active.render()}</main>
        </div>
    )
}
