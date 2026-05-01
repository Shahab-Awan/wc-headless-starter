---
name: Test every phase exhaustively
description: Between every implementation phase, run full E2E + visual verification + Gemini critique. No shortcuts. No proceeding until all tests pass.
type: feedback
---

Every implementation phase must be followed by a complete testing gate before proceeding to the next phase:

1. Full regression suite (curl-suite, cart-lock-race, auth-suite, cro-matrix, e2e-smoke, full-journey)
2. Playwright E2E human-emulated testing of any changed surface
3. Screenshot capture at multiple viewports (desktop + mobile) × themes (light + dark)
4. Gemini 2.5 Pro visual critique with structured scoring on any customer-facing or admin-facing UI
5. Fix any issues found before proceeding

**Why:** The user caught multiple visual regressions that automated tests didn't catch (illegible FK text, chopped headers, theme flash). Every phase produces potential visual regressions that compound if not caught immediately.

**How to apply:** After completing each numbered step in the implementation plan, stop and run the full test battery. Only proceed to the next step after all tests pass and Gemini scores are acceptable. Document results in the commit message.
