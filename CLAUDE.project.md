# Lundflix Project Rules

## Quality Bar

- Finish requested work completely. No half measures.
- Prefer clear code over comments. Add comments only when the logic is genuinely non-obvious.
- When Livewire, Flux, Alpine, Blaze, Filament, or other core Laravel ecosystem packages seem broken, assume the integration is wrong before blaming the package.

## Workflow

- Never commit or push without explicit user permission.
- For code changes, run the smallest relevant verification before stopping.
- Preferred verification commands:
  - PHP formatting: `vendor/bin/pint --dirty`
  - PHP static analysis: `composer phpstan`
  - PHP tests: `php artisan test --compact <target>`
  - Frontend formatting: `npm run format:check`
  - Frontend lint: `npm run lint`
- If a change depends on new environment variables, update `.env.example`.
- The app is served by Laravel Herd. Do not try to bootstrap another local server.
- Before merging a branch, rebase it onto `main`. If conflicts appear, stop and show them to the user instead of auto-resolving.

## Backend Conventions

- Use Laravel, Eloquent, Livewire, Flux, and Alpine patterns already present in the repo.
- Interactivity belongs in Livewire and Alpine. Do not derive server-known state from DOM queries.
- Models are globally unguarded. Do not add `$fillable` or `$guarded`.
- Computed attributes accessed multiple times on the same model instance should use `->shouldCache()`.
- Use Carbon comparison helpers instead of comparing formatted date strings.
- All user-facing timezone conversion and airdate logic must go through `App\Support\UserTime` and `App\Support\AirDateTime`.

## UI Conventions

- Brand the app as `lundflix`.
- Conversational user-facing copy uses Lundberghese strings from `lang/en/lundbergh.php`. Short labels, Filament admin text, and standard HTTP error pages are exempt.
- Dark mode is permanent. Use dark colors directly and do not add `dark:` classes.
- Never use inline `style` attributes.
- Do not add emojis to files unless the user requests it. Exception: the app footer emoji in `resources/views/components/layouts/app.blade.php` is intentional branding.
- Blade directives do not work inside component attribute strings. Use `{{ Js::from(...) }}` instead of `@js(...)`.
- **Glassy UI — no solid colors.** All backgrounds (including active/highlighted states) must use transparency and `backdrop-blur-sm` to maintain a translucent aesthetic. For example, use `bg-lundflix/80 backdrop-blur-sm` instead of `bg-lundflix`. Inactive states use `bg-white/10 backdrop-blur-sm`. This applies to buttons, badges, pills, cards, and any other UI elements. Exceptions: the shared app footer in `resources/views/components/layouts/app.blade.php` may use `bg-black`, and the submit button in `resources/views/components/cart.blade.php` may use an opaque branded fill without `backdrop-blur-sm`.
