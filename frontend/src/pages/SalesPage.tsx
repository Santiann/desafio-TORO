import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ApiError, api } from '../api/client'
import type { Campaign, Product, SaleFilters, SaleSummary, Seller } from '../api/types'
import { ErrorBox, Notice } from '../components/Feedback'
import { formatDateTime, formatPoints, toDecimal, toInteger } from '../format'

type SaleForm = {
    externalId: string
    campaignId: string
    sellerId: string
    productId: string
    quantity: string
    unitValue: string
}

const EMPTY_SALE: SaleForm = {
    externalId: '',
    campaignId: '',
    sellerId: '',
    productId: '',
    quantity: '1',
    unitValue: '0.00',
}

const NO_FILTERS: SaleFilters = { campaign_id: '', seller_id: '', status: '' }
const PAGE_SIZE = 20

export default function SalesPage() {
    const [products, setProducts] = useState<Product[]>([])
    const [campaigns, setCampaigns] = useState<Campaign[]>([])
    const [sellers, setSellers] = useState<Seller[]>([])
    const [referenceError, setReferenceError] = useState<unknown>(null)

    const [saleForm, setSaleForm] = useState<SaleForm>(EMPTY_SALE)
    const [saleError, setSaleError] = useState<unknown>(null)
    const [saleNotice, setSaleNotice] = useState<string | null>(null)
    const [salePending, setSalePending] = useState(false)

    const [cancelId, setCancelId] = useState('')
    const [cancelError, setCancelError] = useState<unknown>(null)
    const [cancelNotice, setCancelNotice] = useState<string | null>(null)
    const [cancelPending, setCancelPending] = useState(false)

    const [sales, setSales] = useState<SaleSummary[]>([])
    const [total, setTotal] = useState(0)
    const [filters, setFilters] = useState<SaleFilters>(NO_FILTERS)
    const [offset, setOffset] = useState(0)
    const [listError, setListError] = useState<unknown>(null)

    const loadReferences = useCallback(async () => {
        try {
            const [productList, campaignList, sellerList] = await Promise.all([
                api.listProducts(),
                api.listCampaigns(),
                api.listSellers(),
            ])

            setProducts(productList.data)
            setCampaigns(campaignList.data)
            setSellers(sellerList.data)
            setReferenceError(null)
        } catch (failure) {
            setReferenceError(failure)
        }
    }, [])

    const loadSales = useCallback(async () => {
        try {
            const page = await api.listSales(filters, PAGE_SIZE, offset)

            setSales(page.data)
            setTotal(page.pagination.total)
            setListError(null)
        } catch (failure) {
            setListError(failure)
        }
    }, [filters, offset])

    useEffect(() => {
        void loadReferences()
    }, [loadReferences])

    useEffect(() => {
        void loadSales()
    }, [loadSales])

    async function refresh() {
        const campaignList = await api.listCampaigns()

        setCampaigns(campaignList.data)
        await loadSales()
    }

    async function submitSale(event: FormEvent) {
        event.preventDefault()
        setSaleError(null)
        setSaleNotice(null)
        setSalePending(true)

        try {
            const sale = await api.registerSale({
                external_id: saleForm.externalId,
                campaign_id: toInteger(saleForm.campaignId),
                seller_id: toInteger(saleForm.sellerId),
                product_id: toInteger(saleForm.productId),
                quantity: toInteger(saleForm.quantity),
                unit_value: toDecimal(saleForm.unitValue),
            })

            setSaleNotice(
                sale.duplicate
                    ? `a venda ${sale.external_id} já tinha sido lançada, nada foi pontuado de novo`
                    : `venda ${sale.external_id} lançada para ${sellerName(sale.seller_id)}`,
            )

            if (!sale.duplicate) {
                setSaleForm({ ...EMPTY_SALE, campaignId: saleForm.campaignId, sellerId: saleForm.sellerId })
            }

            await refresh()
        } catch (failure) {
            setSaleError(failure)
        } finally {
            setSalePending(false)
        }
    }

    async function cancel(externalId: string) {
        setCancelError(null)
        setCancelNotice(null)
        setCancelPending(true)

        try {
            const sale = await api.cancelSale(externalId)

            setCancelNotice(
                sale.already_canceled
                    ? `a venda ${sale.external_id} já estava cancelada, nenhum estorno novo foi feito`
                    : `venda ${sale.external_id} cancelada e pontos estornados`,
            )
            setCancelId('')

            await refresh()
        } catch (failure) {
            setCancelError(
                failure instanceof ApiError && failure.status === 404
                    ? new ApiError(404, 'not_found', 'não existe venda com esse external_id')
                    : failure,
            )
        } finally {
            setCancelPending(false)
        }
    }

    function sellerName(id: number): string {
        return sellers.find((seller) => seller.id === id)?.name ?? `vendedor ${id}`
    }

    function campaignName(id: number): string {
        return campaigns.find((campaign) => campaign.id === id)?.name ?? `campanha ${id}`
    }

    function productName(id: number): string {
        return products.find((product) => product.id === id)?.name ?? `produto ${id}`
    }

    function changeFilter(patch: Partial<SaleFilters>) {
        setFilters({ ...filters, ...patch })
        setOffset(0)
    }

    const first = sales.length === 0 ? 0 : offset + 1

    return (
        <section>
            <h2>Vendas</h2>

            <ErrorBox error={referenceError} />

            <form onSubmit={submitSale} className="card">
                <h3>lançar venda</h3>

                <div className="row">
                    <div>
                        <label htmlFor="external-id">external_id</label>
                        <input
                            id="external-id"
                            value={saleForm.externalId}
                            onChange={(event) => setSaleForm({ ...saleForm, externalId: event.target.value })}
                            maxLength={64}
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="campaign">campanha</label>
                        <select
                            id="campaign"
                            value={saleForm.campaignId}
                            onChange={(event) => setSaleForm({ ...saleForm, campaignId: event.target.value })}
                            required
                        >
                            <option value="">selecione</option>
                            {campaigns.map((campaign) => (
                                <option key={campaign.id} value={campaign.id}>
                                    {campaign.name} ({formatPoints(campaign.budget_used)}/
                                    {formatPoints(campaign.budget_total)})
                                    {campaign.status === 'active' ? '' : ' (encerrada)'}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="product">produto</label>
                        <select
                            id="product"
                            value={saleForm.productId}
                            onChange={(event) => setSaleForm({ ...saleForm, productId: event.target.value })}
                            required
                        >
                            <option value="">selecione</option>
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.name} - {product.points_per_unit} pts
                                    {product.active ? '' : ' (inativo)'}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="seller">vendedor</label>
                        <select
                            id="seller"
                            value={saleForm.sellerId}
                            onChange={(event) => setSaleForm({ ...saleForm, sellerId: event.target.value })}
                            required
                        >
                            <option value="">selecione</option>
                            {sellers.map((seller) => (
                                <option key={seller.id} value={seller.id}>
                                    {seller.name} - {seller.email}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="quantity">quantidade</label>
                        <input
                            id="quantity"
                            inputMode="numeric"
                            value={saleForm.quantity}
                            onChange={(event) => setSaleForm({ ...saleForm, quantity: event.target.value })}
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="unit-value">valor unitário</label>
                        <input
                            id="unit-value"
                            inputMode="decimal"
                            value={saleForm.unitValue}
                            onChange={(event) => setSaleForm({ ...saleForm, unitValue: event.target.value })}
                            required
                        />
                    </div>
                </div>

                <div className="actions">
                    <button type="submit" disabled={salePending}>
                        lançar
                    </button>
                </div>

                <ErrorBox error={saleError} />
                <Notice message={saleNotice} />
            </form>

            <form
                onSubmit={(event) => {
                    event.preventDefault()
                    void cancel(cancelId)
                }}
                className="card"
            >
                <h3>cancelar venda por external_id</h3>

                <div className="row">
                    <div>
                        <label htmlFor="cancel-external-id">external_id</label>
                        <input
                            id="cancel-external-id"
                            value={cancelId}
                            onChange={(event) => setCancelId(event.target.value)}
                            maxLength={64}
                            required
                        />
                    </div>
                </div>

                <div className="actions">
                    <button type="submit" disabled={cancelPending}>
                        cancelar venda
                    </button>
                </div>

                <ErrorBox error={cancelError} />
                <Notice message={cancelNotice} />
            </form>

            <h3>vendas lançadas</h3>

            <div className="toolbar">
                <label htmlFor="filter-campaign">campanha</label>
                <select
                    id="filter-campaign"
                    value={filters.campaign_id}
                    onChange={(event) => changeFilter({ campaign_id: event.target.value })}
                >
                    <option value="">todas</option>
                    {campaigns.map((campaign) => (
                        <option key={campaign.id} value={campaign.id}>
                            {campaign.name}
                        </option>
                    ))}
                </select>

                <label htmlFor="filter-seller">vendedor</label>
                <select
                    id="filter-seller"
                    value={filters.seller_id}
                    onChange={(event) => changeFilter({ seller_id: event.target.value })}
                >
                    <option value="">todos</option>
                    {sellers.map((seller) => (
                        <option key={seller.id} value={seller.id}>
                            {seller.name}
                        </option>
                    ))}
                </select>

                <label htmlFor="filter-status">situação</label>
                <select
                    id="filter-status"
                    value={filters.status}
                    onChange={(event) => changeFilter({ status: event.target.value })}
                >
                    <option value="">todas</option>
                    <option value="approved">aprovadas</option>
                    <option value="canceled">canceladas</option>
                </select>

                <button type="button" onClick={() => changeFilter(NO_FILTERS)}>
                    limpar filtros
                </button>
            </div>

            <ErrorBox error={listError} />

            <table>
                <thead>
                    <tr>
                        <th>external_id</th>
                        <th>campanha</th>
                        <th>vendedor</th>
                        <th>produto</th>
                        <th>qtd</th>
                        <th>valor unit.</th>
                        <th>pontos</th>
                        <th>situação</th>
                        <th>lançada em</th>
                        <th>ações</th>
                    </tr>
                </thead>
                <tbody>
                    {sales.length === 0 && (
                        <tr>
                            <td colSpan={10}>nenhuma venda encontrada</td>
                        </tr>
                    )}
                    {sales.map((sale) => (
                        <tr key={sale.id}>
                            <td>{sale.external_id}</td>
                            <td>{campaignName(sale.campaign_id)}</td>
                            <td>{sellerName(sale.seller_id)}</td>
                            <td>{productName(sale.product_id)}</td>
                            <td>{sale.quantity}</td>
                            <td>{sale.unit_value}</td>
                            <td>{sale.points === null ? '-' : formatPoints(sale.points)}</td>
                            <td>{sale.status === 'approved' ? 'aprovada' : 'cancelada'}</td>
                            <td>{formatDateTime(sale.created_at)}</td>
                            <td className="actions">
                                <button
                                    type="button"
                                    onClick={() => void cancel(sale.external_id)}
                                    disabled={sale.status !== 'approved' || cancelPending}
                                >
                                    cancelar
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="toolbar">
                <button type="button" onClick={() => setOffset(Math.max(0, offset - PAGE_SIZE))} disabled={offset === 0}>
                    anterior
                </button>
                <button
                    type="button"
                    onClick={() => setOffset(offset + PAGE_SIZE)}
                    disabled={offset + PAGE_SIZE >= total}
                >
                    próxima
                </button>
                <span>{sales.length === 0 ? 'nenhuma venda' : `${first} a ${offset + sales.length} de ${total}`}</span>
            </div>
        </section>
    )
}
