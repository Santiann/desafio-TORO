import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ApiError, api } from '../api/client'
import type { Campaign, Product } from '../api/types'
import { ErrorBox, Notice } from '../components/Feedback'
import { formatPoints, toDecimal, toInteger } from '../format'

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

export default function SalesPage() {
    const [products, setProducts] = useState<Product[]>([])
    const [campaigns, setCampaigns] = useState<Campaign[]>([])
    const [referenceError, setReferenceError] = useState<unknown>(null)

    const [saleForm, setSaleForm] = useState<SaleForm>(EMPTY_SALE)
    const [saleError, setSaleError] = useState<unknown>(null)
    const [saleNotice, setSaleNotice] = useState<string | null>(null)
    const [salePending, setSalePending] = useState(false)

    const [cancelId, setCancelId] = useState('')
    const [cancelError, setCancelError] = useState<unknown>(null)
    const [cancelNotice, setCancelNotice] = useState<string | null>(null)
    const [cancelPending, setCancelPending] = useState(false)

    useEffect(() => {
        let active = true

        async function load() {
            try {
                const [productList, campaignList] = await Promise.all([api.listProducts(), api.listCampaigns()])

                if (!active) {
                    return
                }

                setProducts(productList.data)
                setCampaigns(campaignList.data)
                setReferenceError(null)
            } catch (failure) {
                if (active) {
                    setReferenceError(failure)
                }
            }
        }

        void load()

        return () => {
            active = false
        }
    }, [])

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
                    : `venda ${sale.external_id} lançada para o vendedor ${sale.seller_id}`,
            )

            if (!sale.duplicate) {
                setSaleForm({ ...EMPTY_SALE, campaignId: saleForm.campaignId, sellerId: saleForm.sellerId })
            }

            const campaignList = await api.listCampaigns()

            setCampaigns(campaignList.data)
        } catch (failure) {
            setSaleError(failure)
        } finally {
            setSalePending(false)
        }
    }

    async function submitCancel(event: FormEvent) {
        event.preventDefault()
        setCancelError(null)
        setCancelNotice(null)
        setCancelPending(true)

        try {
            const sale = await api.cancelSale(cancelId)

            setCancelNotice(
                sale.already_canceled
                    ? `a venda ${sale.external_id} já estava cancelada, nenhum estorno novo foi feito`
                    : `venda ${sale.external_id} cancelada e pontos estornados`,
            )
            setCancelId('')

            const campaignList = await api.listCampaigns()

            setCampaigns(campaignList.data)
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
                        <label htmlFor="seller">id do vendedor</label>
                        <input
                            id="seller"
                            inputMode="numeric"
                            value={saleForm.sellerId}
                            onChange={(event) => setSaleForm({ ...saleForm, sellerId: event.target.value })}
                            required
                        />
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

            <form onSubmit={submitCancel} className="card">
                <h3>cancelar venda</h3>

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
        </section>
    )
}
