## Summary
- [ ] What changed (short bullets)

## Security checklist
- [ ] Nonce validation added/verified
- [ ] current_user_can checks added/verified
- [ ] Admin override preserved (manage_options)
- [ ] No sensitive logging

## Conflict rule
- [ ] No duplicate functions/classes/wp_ajax handlers
- [ ] No conflict markers left (<<<<<<< ======= >>>>>>>)

## Testing
- [ ] PHP lint: `find . -name "*.php" -print0 | xargs -0 -n 1 php -l`
- [ ] Portal tabs tested (no 403)
- [ ] ATS tested (views/search)

## Notes
- [ ] Premium enforcement preserved (402 server-side)
