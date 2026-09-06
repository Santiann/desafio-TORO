export function formatDateTime(value: string | null): string {
    if (value === null || value === '') {
        return '-'
    }

    const [date, time = ''] = value.replace('T', ' ').split(' ')
    const [year, month, day] = date.split('-')

    if (year === undefined || month === undefined || day === undefined) {
        return value
    }

    return `${day}/${month}/${year} ${time.slice(0, 5)}`.trim()
}

export function formatPoints(value: number): string {
    return value.toLocaleString('pt-BR')
}

export function toInteger(value: string): number | null {
    return /^-?[0-9]+$/.test(value.trim()) ? Number(value) : null
}

export function toDecimal(value: string): number | null {
    const normalized = value.trim().replace(',', '.')

    return /^-?[0-9]+([.][0-9]+)?$/.test(normalized) ? Number(normalized) : null
}
