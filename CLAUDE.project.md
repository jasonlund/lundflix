# Lundflix Project Rules

## NO HALF MEASURES

When given a task, do it completely and properly. Don't take shortcuts, don't suggest lazy alternatives, don't skip steps. If something needs to be done, do it right the first time.

---

## Code Formatting & Quality

**Always run formatters before committing code:**

- `composer pint` - PHP code formatting
- `npm run format` - Prettier for resources/ (Blade, CSS, JS)

**Run static analysis:**

- `composer phpstan` - PHPStan static analysis (level 5)

Code must pass the following checks before being committed:

- `composer pint:test` - PHP formatting
- `npm run format:check` - Prettier formatting
- `composer phpstan` - Static analysis
- `composer audit` - PHP security vulnerabilities
- `npm audit --audit-level=high` - JS security vulnerabilities
- `npm run lint` - No `dark:` Tailwind classes (always dark mode)

## Git Commits

**NEVER commit or push without explicit user permission.**

When committing changes, use this format:

**Message:** Concise imperative statement (e.g., "Upgrade to Livewire 4 with native single-file components")

**Description:** 1-4 bullet points summarizing the changes (use fewer for simple changes):

- What was added/removed/upgraded
- What was migrated or refactored
- What tooling or config was added

**Co-author:** Always include `Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>`

## Database Migrations

- Columns are ALWAYS placed before timestamps (created_at, updated_at)

## Models

- Use `protected $guarded = [];` instead of `$fillable` for mass assignment protection

## Environment Variables

- When adding new environment variables to `.env`, always add them to `.env.example` as well

## Branding

- The app name is always styled as "lundflix" (lowercase)

## Blade Components

- **Blade directives inside component attributes**: `@js()`, `@if()`, and other Blade directives do NOT work inside `<x-component>` attribute strings. Use `{{ Js::from(...) }}` instead of `@js(...)`.

## Inline Styles

- **Never use inline `style` attributes.** All styling must use Tailwind CSS classes. If a utility doesn't exist, create one with `@utility` in `resources/css/app.css` or define a theme token in `@theme`.

## Dark Mode

- Dark mode is permanently forced on — this app has no light mode. Always style for dark mode directly without using `dark:` prefixed Tailwind classes. Use dark colors as the default (e.g., `bg-zinc-900` not `bg-white dark:bg-zinc-900`).

## Date Comparisons

- Use Carbon methods (`->lte()`, `->gte()`, `->isPast()`, `->isBefore()`, etc.) for date comparisons instead of formatting dates as strings and comparing them
- Use `today()` helper for date-only comparisons (returns `Carbon::today()` at 00:00:00)
- `Carbon::parse()` accepts both string dates and Carbon instances, making it safe for mixed data sources (API arrays vs. Eloquent models)
- Guard against null and empty string before calling `Carbon::parse()` — use `empty($date)` when data originates from external APIs
- Formatting dates as strings for serialization (e.g., `->format('Y-m-d')` in Livewire `dehydrate()`) is distinct from comparison and remains correct

## Livewire Validation

### Convention: Use `#[Validate]` Attributes

For Livewire 4, use `#[Validate]` attributes to declare validation rules:

```php
use Livewire\Attributes\Validate;

#[Validate('required|email')]
public string $email = '';

#[Validate('required|min:8')]
public string $password = '';
```

- Use `wire:model.blur` for real-time validation on blur
- Always call `$this->validate()` in action methods before persisting data
- Use `rules()` method when Laravel Rule objects are needed (e.g., `Password::defaults()`)
- Display errors with `<flux:error name="fieldName" />`

### Password Validation

- Always use `Password::defaults()` for password validation
- Password defaults are configured in `AppServiceProvider::boot()`
- Never hardcode password rules - always reference `Password::defaults()`

### Validation Testing

- Every validation rule must have a corresponding test
- Use Pest datasets for testing multiple invalid inputs
- Test both submission validation and real-time (blur) validation

---

## Enums

- Enums used in Filament tables, infolists, or forms must implement `HasLabel` from `Filament\Support\Contracts\`. Optionally implement `HasColor`, `HasIcon`, and/or `HasDescription` when the enum needs colors, icons, or descriptions in the UI. This lets Filament auto-detect these values — never manually map them in table columns or infolist entries.

## Livewire Best Practices (Project-Specific)

- Avoid `@php` blocks in Livewire Blade templates. Since Livewire SFCs have PHP and Blade in the same file, put all PHP logic in the class section and call methods from the template (e.g., `{{ $this->getEpisodeCode($episode) }}`).

### Convention: Use `#[Computed]` for Repeated Template Calls

When a method is called 2+ times in a Livewire Blade template, use the `#[Computed]` attribute to cache the result for the duration of the request:

```php
use Livewire\Attributes\Computed;

#[Computed]
public function networkInfo(): ?array
{
    // Called multiple times in template - cached after first call
    return $this->show->network ? [...] : null;
}
```

- Methods called once don't need `#[Computed]` - the overhead is unnecessary
- Since `@php` blocks are discouraged, `#[Computed]` is the preferred way to avoid redundant method calls
- Import: `use Livewire\Attributes\Computed;`

## Tailwind Dark Mode (Project Override)

- Dark mode is permanently enabled in this application — there is no light mode. The app always renders in dark mode.
- **Never use `dark:` prefixed Tailwind classes.** Since dark mode is always on, use the dark variant colors directly as the default (e.g., use `bg-zinc-900` instead of `bg-white dark:bg-zinc-900`).
- All new components and pages must be styled for dark mode only.

## Tailwind Important Modifier

**NEVER use Tailwind's `!` (important) modifier unless absolutely unavoidable.** Using `!important` is a code smell that indicates a deeper problem with CSS specificity or component design.

When you encounter a situation where `!` seems necessary:

1. **Prefer publishing Flux components** - Use `php artisan flux:publish` to customize components with proper props or variants
2. **Create a custom component** - Build a new component for your specific use case
3. **Use CSS custom properties** - Override design tokens in `@theme` instead of fighting specificity

If `!important` is truly unavoidable (extremely rare), document why in a code comment explaining:
- What you tried instead
- Why those alternatives didn't work
- What would need to change to remove the `!important`

## Laravel Scout

- Scout provides full-text search for Eloquent models using drivers like Meilisearch, Algolia, or database.
- Use the `search-docs` tool for version-specific Scout documentation.
- Add the `Searchable` trait to models that need search functionality.
- Use `php artisan scout:import` to index existing records.
- Use `Model::search('query')` to perform searches.

### Searchable Models

```php
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}
```

### Searching

```php
$posts = Post::search('laravel')->get();
$posts = Post::search('laravel')->where('user_id', 1)->get();
$posts = Post::search('laravel')->paginate(15);
```

## Laravel Horizon

- Horizon provides a dashboard and configuration for Laravel Redis queues.
- Use the `search-docs` tool for version-specific Horizon documentation.
- Access the dashboard at `/horizon` (protected by `HorizonServiceProvider` authorization).
- Configure queue workers and supervisors in `config/horizon.php`.

### Configuration

- Horizon configuration lives in `config/horizon.php`.
- Define environments, supervisors, and queue settings there.
- Use `php artisan horizon` to start the Horizon process.
- Use `php artisan horizon:terminate` for graceful shutdown during deployments.

## Filament 5

- Filament is an admin panel and form/table builder for Laravel.
- Use the `search-docs` tool for version-specific Filament documentation.
- Resources are static classes that build CRUD interfaces for Eloquent models.

### Panels

- Configure panels in a PanelProvider (e.g., `AdminPanelProvider`).
- Use `php artisan make:filament-panel` to create new panels.
- Enable database notifications with `->databaseNotifications()` in panel configuration.

### Resources

- Use `php artisan make:filament-resource` to generate resources.
- Resources include tables, forms, and pages for managing Eloquent models.
- Follow existing resource patterns in the codebase.

### Testing

- **All Filament resource pages require test coverage.** For each resource, test:
  - List page renders successfully
  - View page renders successfully (if applicable)
  - Data displays correctly in tables and infolists
  - Policy enforcement (create/edit/delete actions hidden when denied)
- Tests live in `tests/Feature/Filament/` and use `Livewire::test()` for page components.
- For multi-tenant panels, call `Filament::bootCurrentPanel()` after setting the tenant.

```php
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['plex_token' => 'admin-token']);
    $this->actingAs($this->admin);
});

it('can render the list page', function () {
    Livewire::test(ListMovies::class)->assertSuccessful();
});

it('does not show create button due to policy', function () {
    Livewire::test(ListMovies::class)->assertDontSee('New movie');
});
```

## Laravel Pennant

- Pennant is a lightweight feature flag package for incremental rollouts and A/B testing.
- Use the `search-docs` tool for version-specific Pennant documentation.
- Prefer class-based features over closure-based definitions.

### Defining Features

- Use `php artisan pennant:feature FeatureName` to create class-based features in `app/Features/`.
- Features resolve against a scope (usually the authenticated user).

```php
namespace App\Features;

use App\Models\User;

class NewDashboard
{
    public function resolve(User $user): bool
    {
        return $user->is_beta_tester;
    }
}
```

### Checking Features

```php
use Laravel\Pennant\Feature;

// Check if active
Feature::active(NewDashboard::class);

// Conditional execution
Feature::when(NewDashboard::class,
    fn () => /* feature active */,
    fn () => /* feature inactive */,
);
```

### Blade Directive

```blade
@feature(App\Features\NewDashboard::class)
    <x-new-dashboard />
@else
    <x-old-dashboard />
@endfeature
```

### Testing

```php
use Laravel\Pennant\Feature;

Feature::define(NewDashboard::class, true);
expect(Feature::active(NewDashboard::class))->toBeTrue();
```
