---
name: review
description: Adversarial multi-agent code review. Spawns parallel subagents to review the working copy against main for discipline, conventions, edge cases, integration risks, and optionally Linear ticket requirements.
---

# Adversarial Code Review

You are orchestrating a structured, multi-phase code review using parallel subagents with isolated context windows.

## Input

- **Ticket ID** (optional): Linear ticket in format `FLIX-###`
- If no arguments provided, review the full working copy diff against `main`

## Phase 1: Context Gathering

1. **Get the working copy diff**:
   ```bash
   git diff main...HEAD --stat
   git diff main...HEAD
   ```
   - Note which files were added, modified, deleted
   - Save the full diff as `DIFF_CONTENT`
   - Save the file list as `CHANGED_FILES`

2. **Fetch Linear ticket** (if ticket ID provided):
   - Use the Linear MCP `get_issue` tool to fetch the ticket
   - Get the full description, acceptance criteria, labels, priority
   - Check for linked documents or parent/child relationships
   - Save as `TICKET_CONTEXT`

3. **Load project conventions**:
   - Read `CLAUDE.md` and `CLAUDE.project.md`
   - These will be passed to the conventions-reviewer

## Phase 2: Parallel Review Agents

Spawn these subagents **in parallel** using the Agent tool. Each operates in isolated context.

Pass to each agent: the `CHANGED_FILES` list, the `DIFF_CONTENT`, and any additional context specific to that agent's role.

### Agents to spawn:

1. **discipline-reviewer** — Engineering discipline: simplicity, surgical precision, goal-driven execution
2. **conventions-reviewer** — Codebase conventions and CLAUDE.md rule compliance
3. **edge-case-reviewer** — Adversarial failure mode and boundary analysis
4. **integration-reviewer** — Blast radius and side effect analysis

5. **requirements-reviewer** (only if `TICKET_CONTEXT` exists) — Validates changes against ticket acceptance criteria. Pass the `TICKET_CONTEXT`.

### Agent Prompt Template

For each agent, include in the prompt:
- The agent's role description from its agent file
- The changed files list
- The diff content (or relevant portions for large diffs)
- Instruction to return findings in the structured format defined in the agent file

## Phase 3: Synthesis

After all Phase 2 agents complete:

1. Collect all findings from agent responses
2. Deduplicate findings that overlap (keep the more specific one)
3. Group findings by file path
4. Create a `PRELIMINARY_FINDINGS` list

## Phase 4: Adversarial Verification

Spawn 2 challenger subagents **in parallel**:

1. **false-positive-hunter** — Reviews each finding and argues why it might be wrong
2. **missing-defect-hunter** — Reviews the changes fresh, looking for issues all other agents missed

Pass to both:
- The `CHANGED_FILES` and `DIFF_CONTENT`
- The `PRELIMINARY_FINDINGS` list

## Phase 5: Final Report

Consolidate all verified findings into a structured report.

### Determine Final Verdicts

For each preliminary finding:
- If false-positive-hunter says **dismiss** with good evidence: move to Dismissed
- If false-positive-hunter says **downgrade**: adjust severity
- If false-positive-hunter says **uphold**: keep as-is

Add any new findings from missing-defect-hunter.

### Classify Findings

- **Blocking** = critical severity findings that survived verification
- **Should Fix** = major severity findings
- **Consider** = minor severity findings
- **Dismissed** = findings the false-positive-hunter successfully challenged

### Report Structure

```markdown
# Code Review: [branch name]

## Summary
- **Blocking Issues:** {count}
- **Should Fix:** {count}
- **Consider:** {count}
- **Dismissed:** {count}

## Blocking Issues (must fix)

[For each finding:]
- **File:** `path/to/file` (lines N-M)
- **Issue:** [description]
- **Evidence:** [code quote or rule citation]
- **Fix:** [specific recommendation]
- **Found by:** [agent name]

## Should Fix (strongly recommended)

[Same format]

## Consider (author's judgment)

[Same format]

## Dismissed Findings

[For each dismissed finding:]
- **Original Finding:** [brief description]
- **Dismissed Because:** [reason from false-positive-hunter]

## Coverage Notes

[From missing-defect-hunter:]
- Areas that weren't fully covered
- Suggestions for additional review focus
```

## Orchestration Notes

- Do not summarize subagent work in main context — trust the isolated context
- If a subagent fails or times out, note it in the report and proceed with available results
- Total findings should trace to either: ticket requirements, engineering discipline principles, or codebase conventions
- If you can't cite the authority for a finding, it doesn't belong in the report
- For large diffs (>500 lines), consider splitting the diff across agents by file area rather than sending the full diff to each
