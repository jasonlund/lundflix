import.meta.glob(['../images/**/*.{jpg,jpeg,png,gif,webp,avif,svg,webm}'])
import './cart.js'

document.addEventListener('livewire:init', () => {
    Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            const errorConfig = window.lundflixErrors?.[response.status]
            if (errorConfig) {
                preventDefault()
                const traceId = response.headers.get('X-Trace-Id') || null
                const video = errorConfig.videos[Math.floor(Math.random() * errorConfig.videos.length)]
                window.dispatchEvent(
                    new CustomEvent('error-overlay-show', {
                        detail: {
                            status: response.status,
                            traceId,
                            message: errorConfig.message,
                            description: errorConfig.description,
                            ...video,
                        },
                    }),
                )
            }
        })
    })
})
