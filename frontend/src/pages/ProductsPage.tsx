import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { api } from '../api/client'
import type { Product } from '../api/types'
import { ErrorBox, Notice } from '../components/Feedback'
import { formatDateTime, toInteger } from '../format'

type FormState = {
    name: string
    sku: string
    pointsPerUnit: string
    active: boolean
}

const EMPTY_FORM: FormState = { name: '', sku: '', pointsPerUnit: '', active: true }

export default function ProductsPage() {
    const [products, setProducts] = useState<Product[]>([])
    const [form, setForm] = useState<FormState>(EMPTY_FORM)
    const [editingId, setEditingId] = useState<number | null>(null)
    const [listError, setListError] = useState<unknown>(null)
    const [formError, setFormError] = useState<unknown>(null)
    const [notice, setNotice] = useState<string | null>(null)
    const [pending, setPending] = useState(false)

    const load = useCallback(async () => {
        try {
            const response = await api.listProducts()

            setProducts(response.data)
            setListError(null)
        } catch (failure) {
            setListError(failure)
        }
    }, [])

    useEffect(() => {
        void load()
    }, [load])

    function reset() {
        setForm(EMPTY_FORM)
        setEditingId(null)
        setFormError(null)
    }

    function edit(product: Product) {
        setForm({
            name: product.name,
            sku: product.sku,
            pointsPerUnit: String(product.points_per_unit),
            active: product.active,
        })
        setEditingId(product.id)
        setFormError(null)
        setNotice(null)
    }

    async function submit(event: FormEvent) {
        event.preventDefault()
        setFormError(null)
        setNotice(null)
        setPending(true)

        const input = {
            name: form.name,
            sku: form.sku,
            points_per_unit: toInteger(form.pointsPerUnit),
            active: form.active,
        }

        try {
            if (editingId === null) {
                const created = await api.createProduct(input)

                setNotice(`produto ${created.sku} criado`)
            } else {
                const updated = await api.updateProduct(editingId, input)

                setNotice(`produto ${updated.sku} atualizado`)
            }

            reset()
            await load()
        } catch (failure) {
            setFormError(failure)
        } finally {
            setPending(false)
        }
    }

    async function deactivate(product: Product) {
        if (!window.confirm(`inativar o produto ${product.sku}?`)) {
            return
        }

        setFormError(null)
        setNotice(null)

        try {
            await api.deactivateProduct(product.id)
            setNotice(`produto ${product.sku} inativado`)
            await load()
        } catch (failure) {
            setFormError(failure)
        }
    }

    return (
        <section>
            <h2>Produtos</h2>

            <form onSubmit={submit} className="card">
                <h3>{editingId === null ? 'novo produto' : `editando produto #${editingId}`}</h3>

                <div className="row">
                    <div>
                        <label htmlFor="name">nome</label>
                        <input
                            id="name"
                            value={form.name}
                            onChange={(event) => setForm({ ...form, name: event.target.value })}
                            maxLength={150}
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="sku">sku</label>
                        <input
                            id="sku"
                            value={form.sku}
                            onChange={(event) => setForm({ ...form, sku: event.target.value })}
                            maxLength={64}
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="points">pontos por unidade</label>
                        <input
                            id="points"
                            inputMode="numeric"
                            value={form.pointsPerUnit}
                            onChange={(event) => setForm({ ...form, pointsPerUnit: event.target.value })}
                            required
                        />
                    </div>

                    <div className="checkbox">
                        <label htmlFor="active">
                            <input
                                id="active"
                                type="checkbox"
                                checked={form.active}
                                onChange={(event) => setForm({ ...form, active: event.target.checked })}
                            />
                            ativo
                        </label>
                    </div>
                </div>

                <div className="actions">
                    <button type="submit" disabled={pending}>
                        {editingId === null ? 'criar' : 'salvar'}
                    </button>
                    {editingId !== null && (
                        <button type="button" onClick={reset}>
                            cancelar edição
                        </button>
                    )}
                </div>

                <ErrorBox error={formError} />
                <Notice message={notice} />
            </form>

            <ErrorBox error={listError} />

            <table>
                <thead>
                    <tr>
                        <th>id</th>
                        <th>nome</th>
                        <th>sku</th>
                        <th>pontos</th>
                        <th>situação</th>
                        <th>criado em</th>
                        <th>ações</th>
                    </tr>
                </thead>
                <tbody>
                    {products.length === 0 && (
                        <tr>
                            <td colSpan={7}>nenhum produto cadastrado</td>
                        </tr>
                    )}
                    {products.map((product) => (
                        <tr key={product.id}>
                            <td>{product.id}</td>
                            <td>{product.name}</td>
                            <td>{product.sku}</td>
                            <td>{product.points_per_unit}</td>
                            <td>{product.active ? 'ativo' : 'inativo'}</td>
                            <td>{formatDateTime(product.created_at)}</td>
                            <td className="actions">
                                <button type="button" onClick={() => edit(product)}>
                                    editar
                                </button>
                                <button type="button" onClick={() => void deactivate(product)} disabled={!product.active}>
                                    inativar
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    )
}
