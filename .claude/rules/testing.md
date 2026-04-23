# Verification

- Do not claim a change works without evidence.
- After repo changes, run the smallest relevant automated verification before stopping.
- Minimum expectations:
  - PHP changes: `vendor/bin/pint --dirty` and targeted `php artisan test --compact`
  - Blade, CSS, or JavaScript changes: `npm run format:check` and `npm run lint`, plus targeted tests or builds when needed
  - Claude context and hook changes: validate JSON, syntax-check hook scripts, and run targeted tests that cover the configuration layout
- If verification cannot run after code changes, note `VERIFICATION_SKIPPED: <reason>` inline — never as the final standalone message.
- For non-trivial changes, run the `verification-reviewer` subagent before reporting completion.

## Browser Testing

- When instructed to use or test in a browser, always use Chrome DevTools MCP tools.
- Default login credentials: `admin@lundflix.com` / `password` (seeded admin user), unless the user specifies otherwise.
- If DevTools is busy, hung, or unresponsive, do NOT give up. Kill the blocking process and retry. You have the ability to recover and must always attempt it.
- Only pause and prompt the user if all recovery attempts have been exhausted and the task genuinely cannot proceed.
- Always complete the full instructed browser task fully before stopping. No partial work.

## Plan Mode Permissions

- Plan mode restricts edits and non-readonly actions by default.
- However, if the user **explicitly instructs** you to perform a specific action during plan mode (e.g., clear database entries, use Chrome DevTools MCP, run an MCP tool, execute a command), you have permission to do **exactly** what was instructed — nothing more.
- Do not extrapolate or expand beyond the explicit instruction. Only perform the specific action the user described.
