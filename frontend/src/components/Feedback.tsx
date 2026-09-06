import { ApiError } from '../api/client'

export function ErrorBox({ error }: { error: unknown }) {
    if (error === null || error === undefined) {
        return null
    }

    const fields = error instanceof ApiError ? Object.entries(error.fields) : []
    const message = error instanceof ApiError
        ? error.message
        : 'falha inesperada, tente novamente'

    return (
        <div className="feedback error" role="alert">
            <p>{message}</p>
            {fields.length > 0 && (
                <ul>
                    {fields.map(([field, detail]) => (
                        <li key={field}>
                            <strong>{field}</strong>: {detail}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    )
}

export function Notice({ message }: { message: string | null }) {
    if (message === null) {
        return null
    }

    return (
        <p className="feedback notice" role="status">
            {message}
        </p>
    )
}
