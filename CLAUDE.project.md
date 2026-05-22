# Lundflix Project Rules

## Quality Bar

- Finish requested work completely. No half measures.
- Prefer clear code over comments. Add comments only when the logic is genuinely non-obvious.
- When Livewire, Flux, Alpine, Blaze, Filament, or other core Laravel ecosystem packages seem broken, assume the integration is wrong before blaming the package.

## Workflow

- Never commit or push without explicit user permission.
- Run verification silently after code changes. Only surface failures.
- Preferred verification commands:
  - PHP formatting: `vendor/bin/pint --dirty`
  - PHP static analysis: `composer phpstan`
  - PHP tests: `php artisan test --compact <target>`
  - Frontend formatting: `npm run format:check`
  - Frontend lint: `npm run lint`
- If a change depends on new environment variables, update `.env.example`.
- The app is served by Laravel Herd. Do not try to bootstrap another local server.
- Before merging a branch, rebase it onto `main`. If conflicts appear, stop and show them to the user instead of auto-resolving.
- When creating a new tracked error-video asset from source footage, use `ffmpeg` directly and export the canonical profile: `vp9`, `yuv420p`, `768x432`, `30fps`, video only. Example:

  ```bash
  ffmpeg -y \
    -ss 00:21:47.000 \
    -to 00:21:51.500 \
    -i /path/to/source.mp4 \
    -map 0:v:0 \
    -an -sn -dn \
    -vf "scale=768:432:flags=lanczos,format=yuv420p" \
    -r 30 \
    -c:v libvpx-vp9 \
    -b:v 0 \
    -crf 36 \
    resources/images/errors/403_the_white_lotus.webm
  ```

- For a timestamp span, use `-ss <start>` and `-to <end>` with absolute timestamps from the source file. Example: `-ss 00:21:47.000 -to 00:21:51.500` means “start at 21:47.000 and stop at 21:51.500.” Do not treat `-to` as a duration. Use `-t` only when you intentionally want a duration-based clip instead of a start/end timestamp span.

## Backend Conventions

- Use Laravel, Eloquent, Livewire, Flux, and Alpine patterns already present in the repo.
- Interactivity belongs in Livewire and Alpine. Do not derive server-known state from DOM queries.
- Models are globally unguarded. Do not add `$fillable` or `$guarded`.
- Computed attributes accessed multiple times on the same model instance should use `->shouldCache()`.
- Use Carbon comparison helpers instead of comparing formatted date strings.
- All user-facing timezone conversion and airdate logic must go through `App\Support\UserTime` and `App\Support\AirDateTime`.

## UI Conventions

- Brand the app as `lundflix`.
- Conversational user-facing copy uses Lundberghese strings from `lang/en/lundbergh.php`. Short labels, Filament admin text, standard HTTP error pages, and Slack notifications are exempt.
- Dark mode is permanent. Use dark colors directly and do not add `dark:` classes.
- Never use inline `style` attributes.
- Do not add emojis to files unless the user requests it. Exceptions: the app footer emoji in `resources/views/components/layouts/app.blade.php` and the auth card footer emoji in `resources/views/components/auth-card-footer.blade.php` are intentional branding parity, and regional flag emojis generated from country codes in `resources/views/components/movies/availability.blade.php` are allowed for release metadata.
- **Emoji rendering policy.** When emoji are used in user-facing UI, render them via the `<x-emoji>` Blade component (e.g. `<x-emoji char="🤠" />`, `<x-emoji :country="$iso" />`), which serves Apple-style PNGs from `resources/images/emoji/` for cross-platform consistency. Do not emit raw unicode emoji literals into Blade templates or compute regional-indicator codepoints inline. To add a new emoji, drop the Apple PNG into `resources/images/emoji/` using the lowercase hex codepoint filename (e.g. `1f920.png`, `1f1fa-1f1f8.png`). Exception: Slack notifications (`app/Notifications/*`) may use raw unicode emoji — Slack renders them natively and the PNG component is HTML-only.
- Blade directives do not work inside component attribute strings. Use `{{ Js::from(...) }}` instead of `@js(...)`.
- **No vanilla JS hacks.** Do not reach for `setTimeout`, `MutationObserver`, or similar low-level browser APIs to orchestrate UI behavior. All client-side interactivity can and should be accomplished with Alpine.js (and Livewire for server state).
- **Glassy UI — no solid colors.** All backgrounds (including active/highlighted states) must use transparency and `backdrop-blur-sm` to maintain a translucent aesthetic. For example, use `bg-lundflix/80 backdrop-blur-sm` instead of `bg-lundflix`. Inactive states use `bg-white/10 backdrop-blur-sm`. This applies to buttons, badges, pills, cards, and any other UI elements. Exceptions: the shared app footer in `resources/views/components/layouts/app.blade.php` and the auth card footer in `resources/views/components/auth-card-footer.blade.php` may use `bg-black` for branding parity, the submit button in `resources/views/components/cart.blade.php` may use an opaque branded fill without `backdrop-blur-sm`, and Flux modals (`resources/views/flux/modal/index.blade.php`) may use solid backgrounds for their panel chrome.
