# Conductor Instructions

## Known Issues

### Bolt Emoji Rendering Bug

Conductor has a bug where the ⚡ (high-voltage/bolt) emoji in Livewire 4 single-file component filenames renders as `\342\232\241` (UTF-8 byte sequence) in the diff view, making files unreadable.

**Workaround:** Disabled the emoji convention by setting `'emoji' => false` in `config/livewire.php`.

**When Conductor fixes this bug:**
1. Set `'emoji' => true` in `config/livewire.php`
2. Rename Livewire single-file components to use the ⚡ prefix (e.g., `⚡login.blade.php`)

**Reference:** http://livewire.laravel.com/docs/4.x/components#creating-components

---

## Code Review Workflow

When Conductor leaves comments during the review process, present those comments back to the user using the `mcp__conductor__AskUserQuestion` MCP tool.

**Include in each question:**
- Brief but descriptive summary of the issue
- Applicable file path
- Line number(s)

This allows the user to respond to review feedback interactively rather than having to navigate to each comment manually.
