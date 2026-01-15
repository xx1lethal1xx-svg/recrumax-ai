# TASKS.md — RecruMax / AI Suite (Patch Recipes)

Reguli:
- ADD-ONLY / patch-style
- fără rescrieri masive
- securitate WP: nonce + current_user_can
- admin override: manage_options
- roluri recomandate: rmax_candidate_access / rmax_company_access / rmax_recruiter_access
- premium enforcement server-side (402)

---

## PATCH56-57 (Enterprise Hardening + UX PRO)
### Goal
1) Role-based access pe toate portal/ATS endpoints (nu doar current_user_can('read'))
2) Nonce unificat peste tot (zero 403)
3) Saved Views PRO (rename/default/reset + per-user persistence)
4) Smart Search PRO (candidate/company/job/title/tags/notes + debounce)
5) KPI caching (transients) + invalidare la status changes
6) Logging standardizat pentru auth/nonce fails

### Acceptance
✅ portal tabs fără 403  
✅ saved jobs funcționează  
✅ ATS views persistente  
✅ search rapid + stabil  
✅ php -l OK  
✅ fără duplicate wp_ajax handlers  

---

## PATCH58 — ATS Automation PRO (BestJobs++)
### Goal
- Anti-ghosting reminders (config per stage + delay)
- Activity timeline per candidat/aplicație (status changes, notes, actions)
- Quick actions per stage (send email template, assign recruiter, schedule placeholder)
- Bulk follow-up (select multiple, apply template + log)
- Respect premium enforcement 402 (server-side)

### Acceptance
✅ timeline se vede în UI  
✅ reminder rules funcționează (cron-safe)  
✅ quick actions nu dau 403  
✅ audit log complet  

---

## FIX — Portal 403 / AJAX mismatch
### Checklist rapid
- check_ajax_referer(...) peste tot
- current_user_can(...) corect
- admin override
- wp_send_json_* cu status corect
- JS trimite nonce corect (wp_localize_script)

---

END.
