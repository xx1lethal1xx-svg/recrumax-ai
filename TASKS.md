# TASKS.md — RecruMax / RMax AI Suite (Patch Recipes)

Acest fișier definește task-uri standard pentru Codex (patch-style).
Regulă: ADD-ONLY, fără rescriere masivă.

---

## PATCH55 (Etapa 5): KPI per recruiter + Saved Views + Smart Search

### Goal
Creștem puterea ATS / Portal Recruiter:
- KPI per recruiter (time-to-hire, stage counts, accept rate)
- Saved Views (filtre salvate per user)
- Smart Search (căutare rapidă în candidați/joburi/aplicații)

### Scope (fișiere tipice)
- includes/ (helpers, db queries)
- admin/pages/ (ATS board UI)
- assets/ (JS pentru filters + search)
- AJAX handlers: wp_ajax_rmax_*

### Acceptance Criteria
✅ KPI se calculează corect și rapid (cu caching)  
✅ Saved view se salvează per user + se aplică instant  
✅ Smart search are debounce + rezultat live  
✅ Audit log complet pentru acțiuni  
✅ Nu există 403/nonce mismatch  
✅ Admin are full access override

---

## FIX: Portal tabs 403 / AJAX mismatch

### Symptom
În portal apare 403 la taburi (Candidate/Company/Recruiter).

### Root causes probabile
- nonce invalid sau lipsă
- capability check greșit
- endpoint url greșit
- wp_localize_script nu trimite param corect

### Fix strategy
1) Identifică exact call-ul AJAX (action)
2) Verifică nonce + current_user_can()
3) Unifică nonce-ul în toate portalurile
4) Add toast + fallback message
5) Log: rmax_log('portal_ajax_403', action + user_id + referer)

---

## FEATURE: Admin Full Portal Access (Simulare rol)

### Goal
Admin să poată:
- vedea portal candidate/company fără restricții
- accesa “view as company/candidate” (impersonation safe)

### Rules
- doar manage_options
- log fiecare impersonation
- buton clar “Exit simulation”

---

## QUALITY: Hardening (SAFE-LOAD + no-fatal)

### Goal
Plugin nu mai blochează WP Admin niciodată:
- safe includes
- guard anti redeclare
- fail-soft UI cu loguri

Acceptance:
✅ plugin activează instant  
✅ modul care crapă e izolat și logat  
✅ portal/admin se încarcă fără fatal errors
