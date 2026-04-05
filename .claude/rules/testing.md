# Verification

- Do not claim a change works without evidence.
- After repo changes, run the smallest relevant automated verification before stopping.
- Minimum expectations:
  - PHP changes: `vendor/bin/pint --dirty` and targeted `php artisan test --compact`
  - Blade, CSS, or JavaScript changes: `npm run format:check` and `npm run lint`, plus targeted tests or builds when needed
  - Claude context and hook changes: validate JSON, syntax-check hook scripts, and run targeted tests that cover the configuration layout
- If verification cannot run, say `VERIFICATION_SKIPPED: <reason>` and describe the remaining risk.
- For non-trivial changes, run the `verification-reviewer` subagent before reporting completion.
