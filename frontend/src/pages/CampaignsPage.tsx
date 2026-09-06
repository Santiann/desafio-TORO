import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { api } from '../api/client'
import type { Campaign } from '../api/types'
import { ErrorBox, Notice } from '../components/Feedback'
import { formatDateTime, formatPoints, toInteger } from '../format'

type FormState = {
    name: string
    budgetTotal: string
    startsAt: string
    endsAt: string
}

const EMPTY_FORM: FormState = { name: '', budgetTotal: '', startsAt: '', endsAt: '' }

export default function CampaignsPage() {
    const [campaigns, setCampaigns] = useState<Campaign[]>([])
    const [form, setForm] = useState<FormState>(EMPTY_FORM)
    const [listError, setListError] = useState<unknown>(null)
    const [formError, setFormError] = useState<unknown>(null)
    const [notice, setNotice] = useState<string | null>(null)
    const [pending, setPending] = useState(false)

    const load = useCallback(async () => {
        try {
            const response = await api.listCampaigns()

            setCampaigns(response.data)
            setListError(null)
        } catch (failure) {
            setListError(failure)
        }
    }, [])

    useEffect(() => {
        void load()
    }, [load])

    async function submit(event: FormEvent) {
        event.preventDefault()
        setFormError(null)
        setNotice(null)
        setPending(true)

        try {
            const created = await api.createCampaign({
                name: form.name,
                budget_total: toInteger(form.budgetTotal),
                starts_at: form.startsAt,
                ends_at: form.endsAt,
            })

            setNotice(`campanha ${created.name} criada com verba de ${formatPoints(created.budget_total)} pontos`)
            setForm(EMPTY_FORM)
            await load()
        } catch (failure) {
            setFormError(failure)
        } finally {
            setPending(false)
        }
    }

    return (
        <section>
            <h2>Campanhas</h2>

            <form onSubmit={submit} className="card">
                <h3>nova campanha</h3>

                <div className="row">
                    <div>
                        <label htmlFor="campaign-name">nome</label>
                        <input
                            id="campaign-name"
                            value={form.name}
                            onChange={(event) => setForm({ ...form, name: event.target.value })}
                            maxLength={150}
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="budget">verba total em pontos</label>
                        <input
                            id="budget"
                            inputMode="numeric"
                            value={form.budgetTotal}
                            onChange={(event) => setForm({ ...form, budgetTotal: event.target.value })}
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="starts-at">começa em</label>
                        <input
                            id="starts-at"
                            type="datetime-local"
                            value={form.startsAt}
                            onChange={(event) => setForm({ ...form, startsAt: event.target.value })}
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="ends-at">termina em</label>
                        <input
                            id="ends-at"
                            type="datetime-local"
                            value={form.endsAt}
                            onChange={(event) => setForm({ ...form, endsAt: event.target.value })}
                            required
                        />
                    </div>
                </div>

                <div className="actions">
                    <button type="submit" disabled={pending}>
                        criar
                    </button>
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
                        <th>verba usada / total</th>
                        <th>disponível</th>
                        <th>vigência</th>
                        <th>situação</th>
                    </tr>
                </thead>
                <tbody>
                    {campaigns.length === 0 && (
                        <tr>
                            <td colSpan={6}>nenhuma campanha cadastrada</td>
                        </tr>
                    )}
                    {campaigns.map((campaign) => (
                        <tr key={campaign.id}>
                            <td>{campaign.id}</td>
                            <td>{campaign.name}</td>
                            <td>
                                {formatPoints(campaign.budget_used)} / {formatPoints(campaign.budget_total)}
                            </td>
                            <td>{formatPoints(campaign.budget_total - campaign.budget_used)}</td>
                            <td>
                                {formatDateTime(campaign.starts_at)} a {formatDateTime(campaign.ends_at)}
                            </td>
                            <td>{campaign.status === 'active' ? 'ativa' : 'encerrada'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    )
}
