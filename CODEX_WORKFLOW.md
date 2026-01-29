# CODEX_WORKFLOW.md — How to run Codex on RecruMax safely

## Default instruction (paste into Codex)
Read AGENTS.md and follow it strictly. ADD-ONLY patches only. No large rewrites.

## Always include this conflict rule in prompts
Conflict rule: Never use “Accept both changes” if it duplicates functions/classes/wp_ajax add_action handlers.
If both sides contain useful logic, merge into ONE clean handler and remove duplicates. Run full PHP lint after resolution.

## Recommended run order
1) Healthcheck (AJAX nonce/caps audit + portal 403 fix)
2) PATCH56-57 (role-based access + saved views + smart search + KPI cache)
3) PATCH58 (ATS automation + anti-ghosting + timeline)

## Standard testing commands
- PHP lint:
  find . -name "*.php" -print0 | xargs -0 -n 1 php -l
- Search for wp_ajax handlers:
  grep -R "add_action('wp_ajax" -n .
- Search for conflict markers:
  grep -R --line-number -E '<<<<<<<|=======|>>>>>>>' .

END.
