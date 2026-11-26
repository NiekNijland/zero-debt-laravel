## What and why



## Behavior that changes on purpose

None, or what and why.

## Checklist

- [ ] Ticket and type in the title and every commit (`NOTICKET type: ...` or `PROJ-123 type: ...`)
- [ ] Follows the one-pattern table in `AGENTS.md`; copied the example file
- [ ] No entry added to a list, baseline, suppression, skip or ignore
- [ ] If this PR converges a pattern: the guard that keeps it is in this PR, and its row in `AGENTS.md` and `docs/review-rubric.md`
- [ ] New route: `can:` on the route, `throttle:<limiter>`, its test in the route coverage tests
- [ ] New migration: indexes on foreign keys, `down()` that undoes `up()`
- [ ] Every new string is a key of `lang/` in both languages
- [ ] No comments; only what a future reader would act on
- [ ] A change to `scripts/`, the workflows, the guard folder or `.github/CODEOWNERS`: the commit carries `[ratchet: script] <reason>` and the code owner reviews it
- [ ] Which counter does this change, and by how much? (`bash scripts/debt-dashboard.sh --offline`)
