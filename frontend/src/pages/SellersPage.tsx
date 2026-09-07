import { useEffect, useState } from 'react'
import { api } from '../api/client'
import type { Seller } from '../api/types'
import { ErrorBox } from '../components/Feedback'
import { formatPoints } from '../format'

export default function SellersPage() {
    const [sellers, setSellers] = useState<Seller[]>([])
    const [error, setError] = useState<unknown>(null)

    useEffect(() => {
        let active = true

        async function load() {
            try {
                const response = await api.listSellers()

                if (active) {
                    setSellers(response.data)
                    setError(null)
                }
            } catch (failure) {
                if (active) {
                    setError(failure)
                }
            }
        }

        void load()

        return () => {
            active = false
        }
    }, [])

    return (
        <section>
            <h2>Vendedores</h2>

            <p>
                Saldo de cada carteira, somado do ledger. O extrato linha a linha só o próprio vendedor
                enxerga, na tela dele.
            </p>

            <ErrorBox error={error} />

            <table>
                <thead>
                    <tr>
                        <th>id</th>
                        <th>nome</th>
                        <th>email</th>
                        <th>saldo</th>
                    </tr>
                </thead>
                <tbody>
                    {sellers.length === 0 && (
                        <tr>
                            <td colSpan={4}>nenhum vendedor cadastrado</td>
                        </tr>
                    )}
                    {sellers.map((seller) => (
                        <tr key={seller.id}>
                            <td>{seller.id}</td>
                            <td>{seller.name}</td>
                            <td>{seller.email}</td>
                            <td>{formatPoints(seller.balance)} pts</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    )
}
