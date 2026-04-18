document.addEventListener('alpine:init', () => {
    Alpine.store('cart', {
        movies: [], // int[]
        episodes: [], // { show_id: int, code: string }[]

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem('lundflix_cart') ?? '{}')
                this.movies = saved.movies ?? []
                this.episodes = saved.episodes ?? []
            } catch {}

            // Listen for cart-submitted event from Livewire submit
            Livewire.on('cart-submitted', () => this.clear())
        },

        _persist() {
            localStorage.setItem(
                'lundflix_cart',
                JSON.stringify({
                    movies: this.movies,
                    episodes: this.episodes,
                }),
            )
        },

        get count() {
            return this.movies.length + this.episodes.length
        },

        hasMovie(id) {
            return this.movies.includes(id)
        },

        toggleMovie(id) {
            if (this.hasMovie(id)) {
                this.movies = this.movies.filter((m) => m !== id)
            } else {
                this.movies = [...this.movies, id]
            }
            this._persist()
        },

        countForShow(showId) {
            return this.episodes.filter((e) => e.show_id === showId).length
        },

        syncShowEpisodes(showId, codes) {
            // Remove all episodes for this show, then add back the selected ones
            this.episodes = [
                ...this.episodes.filter((e) => e.show_id !== showId),
                ...codes.map((code) => ({
                    show_id: showId,
                    code: code.toLowerCase(),
                })),
            ]
            this._persist()
        },

        clear() {
            this.movies = []
            this.episodes = []
            this._persist()
        },

        toPayload() {
            return {
                movies: this.movies,
                episodes: this.episodes,
            }
        },
    })
})
