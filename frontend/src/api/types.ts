export type Role = 'admin' | 'seller'

export type AuthUser = {
    id: number
    name: string
    email: string
    role: Role
}

export type LoginResponse = {
    token: string
    user: AuthUser
}

export type Product = {
    id: number
    name: string
    sku: string
    points_per_unit: number
    active: boolean
    created_at: string
}

export type ProductInput = {
    name: string
    sku: string
    points_per_unit: number | null
    active: boolean
}

export type Campaign = {
    id: number
    name: string
    budget_total: number
    budget_used: number
    starts_at: string
    ends_at: string
    status: 'active' | 'closed'
    created_at: string
}

export type ClosedCampaign = Campaign & { already_closed: boolean }

export type CampaignInput = {
    name: string
    budget_total: number | null
    starts_at: string
    ends_at: string
}

export type Seller = {
    id: number
    name: string
    email: string
}

export type Sale = {
    id: number
    external_id: string
    campaign_id: number
    seller_id: number
    product_id: number
    quantity: number
    unit_value: string
    status: 'approved' | 'canceled'
    created_by_user_id: number
    created_at: string
    canceled_at: string | null
}

export type SaleInput = {
    external_id: string
    campaign_id: number | null
    seller_id: number | null
    product_id: number | null
    quantity: number | null
    unit_value: number | null
}

export type RegisteredSale = Sale & { duplicate: boolean }

export type CanceledSale = Sale & { already_canceled: boolean }

export type WalletEntry = {
    id: number
    seller_id: number
    campaign_id: number
    sale_id: number
    type: 'credit' | 'debit'
    points: number
    description: string
    created_at: string
}

export type Wallet = {
    seller_id: number
    balance: number
    pagination: {
        limit: number
        offset: number
        total: number
    }
    data: WalletEntry[]
}

export type Collection<T> = {
    data: T[]
}
