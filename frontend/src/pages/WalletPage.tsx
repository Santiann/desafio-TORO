import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import type { Wallet } from '../api/types'
import { ErrorBox } from '../components/Feedback'
import { formatDateTime, formatPoints } from '../format'

const PAGE_SIZES = [10, 20, 50]

export default function WalletPage() {
    const [wallet, setWallet] = useState<Wallet | null>(null)
    const [limit, setLimit] = useState(20)
    const [offset, setOffset] = useState(0)
    const [error, setError] = useState<unknown>(null)
    const [pending, setPending] = useState(false)

    const load = useCallback(async () => {
        setPending(true)

        try {
            setWallet(await api.wallet(limit, offset))
            setError(null)
        } catch (failure) {
            setError(failure)
        } finally {
            setPending(false)
        }
    }, [limit, offset])

    useEffect(() => {
        void load()
    }, [load])

    const total = wallet?.pagination.total ?? 0
    const shown = wallet?.data.length ?? 0
    const first = shown === 0 ? 0 : offset + 1

    return (
        <section>
            <h2>Minha carteira</h2>

            <ErrorBox error={error} />

            <p className="balance">
                <span>saldo</span>
                <strong>{wallet === null ? '-' : formatPoints(wallet.balance)}</strong>
                <span>pontos</span>
            </p>

            <div className="toolbar">
                <label htmlFor="limit">por página</label>
                <select
                    id="limit"
                    value={limit}
                    onChange={(event) => {
                        setLimit(Number(event.target.value))
                        setOffset(0)
                    }}
                >
                    {PAGE_SIZES.map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </select>

                <button type="button" onClick={() => setOffset(Math.max(0, offset - limit))} disabled={offset === 0}>
                    anterior
                </button>
                <button
                    type="button"
                    onClick={() => setOffset(offset + limit)}
                    disabled={offset + limit >= total}
                >
                    próxima
                </button>
                <button type="button" onClick={() => void load()} disabled={pending}>
                    atualizar
                </button>

                <span className="muted">
                    {shown === 0 ? 'nenhum lançamento' : `${first} a ${offset + shown} de ${total}`}
                </span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>tipo</th>
                        <th>pontos</th>
                        <th>descrição</th>
                        <th>data</th>
                    </tr>
                </thead>
                <tbody>
                    {wallet !== null && wallet.data.length === 0 && (
                        <tr>
                            <td colSpan={4}>o extrato está vazio</td>
                        </tr>
                    )}
                    {wallet?.data.map((entry) => (
                        <tr key={entry.id}>
                            <td>{entry.type === 'credit' ? 'crédito' : 'débito'}</td>
                            <td>
                                {entry.type === 'credit' ? '+' : '-'}
                                {formatPoints(entry.points)}
                            </td>
                            <td>{entry.description}</td>
                            <td>{formatDateTime(entry.created_at)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    )
}
