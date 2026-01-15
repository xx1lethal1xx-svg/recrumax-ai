# AGENTS.md — RecruMax / RMax AI Suite (WordPress) — Rules of Engagement

Acest fișier definește regulile obligatorii pentru orice agent AI (Codex / copilots / auto-fix)
care modifică proiectul RecruMax.

Scop: patch-uri rapide, stabile, securizate și compatibile WordPress, fără rescrieri masive și fără regresii.

---

## 0) Principiul #1: ADD-ONLY / PATCH-STYLE (NU rescrie proiectul)

✅ Permis:
- adăugare de funcții noi
- fix-uri izolate și minimal-invasive
- extensii modulare (fișiere noi, hooks noi)
- refactor local (doar dacă e necesar + sigur)

❌ Interzis:
- rescrierea completă a fișierelor mari
- schimbarea structurii folderelor fără motiv
- ștergerea de cod existent (decât dacă e 100% inutil / dead + confirmat)
- “cleanup” agresiv care strică compatibilitatea

Regulă:
> Dacă un fișier e mare, îl patch-uim punctual, NU îl înlocuim.

---

## 1) Obiectiv proiect (clar & stabil)

RecruMax este un ecosistem peste OLX/Jooble/BestJobs:
- Job board + Aggregator + ATS/CRM + Portaluri Candidate/Company + Monetizare (Stripe/Netopia) + AI (Copilot, scoring, automatizări)
- Public: clean light
- Dashboard: premium dark (cards/bento, modern)

Etapa curentă: **Etapa 5 (Portaluri + ATS Board)**
Next suggested: **Patch55 (KPI per recruiter + drag&drop assign + saved views + smart search)**

---

## 2) Structură proiect (respectă-o strict)

Nu muta fișiere inutil. Menține structura:

- `includes/`
  - `bot-*.php` (boți AI / module independente)
  - helpers / utils
- `admin/pages/` (UI admin, tabs, pagini)
- `assets/` (JS/CSS)
- `core/` (loguri, security, shared)
- `modules/` (componente mari, feature areas)
- `cron.php` (rutine programate)
- `uninstall.php` (clean uninstall)
- `seed/` (seed demo data, dacă există)

---

## 3) WordPress: Security & Safety FIRST

### 3.1 Capabilities / Access Control
- Admin: `manage_options` (sau cap custom dacă există)
- Company portal: cap specific (ex: `rmax_company_access`)
- Candidate portal: cap specific (ex: `rmax_candidate_access`)
- Recruiter/Consultant team: cap specific (ex: `rmax_recruiter_access`)

Regulă:
> Nicio acțiune AJAX/REST fără `current_user_can()` verificat.

### 3.2 Nonce + AJAX
- orice request AJAX trebuie să verifice nonce:
  - `check_ajax_referer('rmax_nonce', 'nonce')` (sau numele real din proiect)
- sanitize & validate:
  - `sanitize_text_field`, `absint`, `sanitize_email`, `wp_kses_post`

### 3.3 Escape output
- HTML: `esc_html`, `esc_attr`
- URLs: `esc_url`
- Textarea: `esc_textarea`

### 3.4 DB safety
- SQL doar cu `$wpdb->prepare()`
- indexuri pentru tabele folosite intens
- nu rula query-uri grele pe fiecare request fără caching

### 3.5 Fără fatal errors
- protejează definițiile cu `function_exists()` / `class_exists()`
- orice include trebuie să fie “safe include” (nu oprește WP Admin)
- fail-soft: UI + log, fără crash

---

## 4) UI/UX Rules (modern, premium, consistent)

### 4.1 Stil dashboard
- dark premium: cards, spacing aerisit, bento layout
- butoane clare, hover states
- loading skeleton / spinner pe AJAX
- toast notifications pentru success/error
- empty states cu CTA

### 4.2 Responsive by default
- layout adaptat mobil
- tabele cu scroll horizontal
- butoane mari, click-friendly

### 4.3 Feedback instant
- pe fiecare acțiune AJAX: disable button + loading + rezultat

---

## 5) Compatibilitate & performanță

- compatibil PHP 7.4+ (ideal 8.0+)
- evită heavy loops fără pagination
- preferă lazy loading & caching
- nu încărca assets global:
  - enqueue JS/CSS doar pe pagina pluginului:
    - `toplevel_page_rmax-ai` (sau slug-ul real)

---

## 6) Loguri & Debug (mandatory)

Orice feature nou trebuie să aibă log entry:
- acțiune executată
- user_id
- rezultat
- error (dacă există)

Nu loga date sensibile (tokens, parole, card info).

Dacă există `rmax_log()` sau sistem intern, folosește-l.

---

## 7) Politică de patch: cum aplici modificările

### 7.1 Workflow minim (recomandat)
1) citește fișierele relevante înainte de editare
2) patch incremental (diferențe mici)
3) verifică:
   - nonce + caps
   - sanitize/escape
   - hook names corecte
4) nu dubla funcții / hooks
5) test mental flow (portal/admin)

### 7.2 Dacă apar buguri
- fix local
- nu introduce framework nou doar pentru 1 bug
- menține compatibilitatea cu patch-urile existente

---

## 8) Convenții naming & coding style

- Prefix: `rmax_` pentru funcții, hooks, options
- Clase: `RMax_*`
- Constante: `RMAX_*`

Options:
- `rmax_*` (ex: `rmax_billing_mode`, `rmax_featured_credits`)

AJAX:
- `wp_ajax_rmax_*`
- `wp_ajax_nopriv_rmax_*` (doar unde e necesar și sigur)

---

## 9) Nu introduce dependențe riscante

❌ Nu adăuga:
- librării externe mari
- pachete composer fără motiv
- “autoupdate” care execută cod remote

✅ Permis:
- fișiere interne helper
- componente UI simple (CSS/JS) în assets

---

## 10) Monetizare enforcement (respectă strict)

Există enforcement server-side 402 pentru:
- Copilot blocat pe Free
- ATS / exports / leads (după patchurile existente)

Regulă:
> Nu oferi funcționalități premium în portal fără check server-side.

UI:
- dacă primești 402 => toast + CTA “Upgrade plan”

---

## 11) Portaluri: reguli obligatorii

- Portal Candidate și Portal Company trebuie să fie accesibile corect
- Admin trebuie să aibă acces complet (full override), fără 403

Regulă:
> Admin = vede tot, poate simula acces.

Dacă apar 403 în portal:
- problema e aproape mereu:
  - nonce lipsă
  - capability check greșit
  - endpoint url greșit
  - mismatch între localized vars și handler

---

## 12) ATS Board: reguli obligatorii

- DnD trebuie să fie stabil:
  - optimistic UI + rollback dacă server error
- bulk actions trebuie să păstreze audit log
- tagging / assign trebuie să fie consistent în DB

Saved views:
- per user
- stocate în options/meta
- safe defaults

---

## 13) GDPR & Compliance (strict)

- minimizează retention
- export & delete data trebuie să fie posibil (admin)
- formulare cu “consimțământ” unde e necesar
- nu trimite date către AI fără minimizare + justificare

---

## 14) AI Modules (Copilot / Scoring / Bots)

- orice apel AI:
  - timeout control
  - retries limitate
  - log minimal
  - fallback text dacă API down

Nu bloca UI.
Preferă async + polling.

---

## 15) COMENZI allowlist (pentru agent local)

✅ Permis:
- `php -v`
- `wp --info`
- `wp plugin list`
- `wp plugin status`
- `wp cache flush`
- `wp rewrite flush`
- `wp cron event list`
- `wp cron event run --due-now`

❌ Interzis:
- comenzi care șterg DB
- `wp db reset`
- exec remote

---

## 16) Template standard pentru task-uri

### FIX / BUG
- Reproduce: [pas cu pas]
- Expected: [ce trebuia]
- Actual: [ce se întâmplă]
- Root cause: [ipoteză]
- Patch: [fișiere + schimbări minime]
- Security: [nonce + caps]
- UX: [toast/loading]
- Logs: [rmax_log entry]

### FEATURE PATCH
Patch name: `PATCHXX`
Goal:
- ce feature adăugăm
Scope:
- fișiere afectate
Constraints:
- add-only
- compatibil WP
- fără rewrite masiv
Acceptance criteria:
- ce trebuie să funcționeze 100%

---

## 17) Regula finală: Stabilitate > feature spam

Dacă trebuie ales între:
- încă 10 funcții noi
sau
- stabilitate + UX perfect + securitate corectă

Alege stabilitatea.

---

## 18) Dacă nu ești sigur unde să inserezi cod

Nu improviza:
- caută hook-uri existente
- folosește pattern-urile din proiect
- preferă funcții noi + hook-uri noi, în loc să strici flow-ul vechi

---

## 19) Check final înainte de “done”

✅ Checklist:
- nu există fatal errors
- nonce/caps OK
- sanitize/escape OK
- assets loaded doar unde trebuie
- UI nu blochează
- loguri există
- portalurile nu dau 403/401
- enforcement premium OK (402 server-side)

---

END.
