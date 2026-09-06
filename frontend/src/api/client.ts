import { clearSession, readSession } from '../auth/session'
import type {
    Campaign,
    CampaignInput,
    CanceledSale,
    Collection,
    LoginResponse,
    Product,
    ProductInput,
    RegisteredSale,
    SaleInput,
    Wallet,
} from './types'

export class ApiError extends Error {
    constructor(
        readonly status: number,
        readonly code: string,
        message: string,
        readonly fields: Record<string, string> = {},
    ) {
        super(message)
        this.name = 'ApiError'
    }
}

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE'

let onUnauthorized: () => void = () => {}

export function setUnauthorizedHandler(handler: () => void): void {
    onUnauthorized = handler
}

async function request<T>(method: Method, path: string, body?: unknown): Promise<T> {
    const session = readSession()
    const headers: Record<string, string> = { Accept: 'application/json' }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json'
    }

    if (session !== null) {
        headers.Authorization = `Bearer ${session.token}`
    }

    let response: Response

    try {
        response = await fetch(`/api${path}`, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
        })
    } catch {
        throw new ApiError(0, 'network_error', 'não foi possível falar com a API')
    }

    const payload = await readJson(response)

    if (response.ok) {
        return payload as T
    }

    // 401 com sessão em mãos é token expirado ou adulterado: derruba e volta ao login.
    // Sem sessão o 401 veio do próprio login e a mensagem da API é o que interessa.
    if (response.status === 401 && session !== null) {
        clearSession()
        onUnauthorized()

        throw new ApiError(401, 'unauthorized', 'sessão expirada, entre novamente')
    }

    if (response.status === 403) {
        throw new ApiError(403, 'forbidden', 'sem permissão para esta ação')
    }

    throw toApiError(response.status, payload)
}

async function readJson(response: Response): Promise<unknown> {
    const text = await response.text()

    if (text === '') {
        return null
    }

    try {
        return JSON.parse(text)
    } catch {
        return null
    }
}

function toApiError(status: number, payload: unknown): ApiError {
    const error = asRecord(asRecord(payload)?.error)
    const code = typeof error?.code === 'string' ? error.code : 'unknown_error'
    const message = typeof error?.message === 'string' ? error.message : 'a API respondeu com um erro inesperado'
    const fields: Record<string, string> = {}

    for (const [field, detail] of Object.entries(asRecord(error?.fields) ?? {})) {
        if (typeof detail === 'string') {
            fields[field] = detail
        }
    }

    return new ApiError(status, code, message, fields)
}

function asRecord(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null ? (value as Record<string, unknown>) : null
}

export const api = {
    login: (email: string, password: string) =>
        request<LoginResponse>('POST', '/auth/login', { email, password }),

    listProducts: () => request<Collection<Product>>('GET', '/products'),

    createProduct: (input: ProductInput) => request<Product>('POST', '/products', input),

    updateProduct: (id: number, input: ProductInput) => request<Product>('PUT', `/products/${id}`, input),

    deactivateProduct: (id: number) => request<Product>('DELETE', `/products/${id}`),

    listCampaigns: () => request<Collection<Campaign>>('GET', '/campaigns'),

    createCampaign: (input: CampaignInput) => request<Campaign>('POST', '/campaigns', input),

    registerSale: (input: SaleInput) => request<RegisteredSale>('POST', '/sales', input),

    cancelSale: (externalId: string) =>
        request<CanceledSale>('POST', `/sales/${encodeURIComponent(externalId)}/cancel`),

    wallet: (limit: number, offset: number) =>
        request<Wallet>('GET', `/me/wallet?limit=${limit}&offset=${offset}`),
}
