<?php

declare(strict_types=1);

namespace App\Support\Concerns;

/**
 * Persists a Livewire paginated component's `$perPage` selection on the
 * authenticated user's `preferences` JSON column.
 *
 * Reads ride along on the already-loaded `auth()->user()` row (zero extra
 * queries). Writes are a single `UPDATE` per change.
 */
trait WithPersistedPerPage
{
    public int $perPage;

    /**
     * Dot-notation key under the user's preferences.
     */
    abstract protected function perPagePreferenceKey(): string;

    /**
     * @return list<int>
     */
    public function perPageOptions(): array
    {
        return [5, 10, 20];
    }

    protected function defaultPerPage(): int
    {
        return $this->perPageOptions()[0];
    }

    /**
     * Livewire pagination name used when resetting the page.
     */
    abstract protected function paginatorPageName(): string;

    public function mountWithPersistedPerPage(): void
    {
        $user = auth()->user();

        $stored = $user
            ? (int) $user->preferences->get($this->perPagePreferenceKey(), $this->defaultPerPage())
            : $this->defaultPerPage();

        $this->perPage = in_array($stored, $this->perPageOptions(), true)
            ? $stored
            : $this->defaultPerPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, $this->perPageOptions(), true)) {
            $this->perPage = $this->defaultPerPage();
        }

        if ($user = auth()->user()) {
            $user->preferences->set($this->perPagePreferenceKey(), $this->perPage);
            $user->save();
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage($this->paginatorPageName());
        }
    }
}
