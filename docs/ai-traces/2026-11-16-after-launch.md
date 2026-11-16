# 2026-11-16 — After launch

Live testing on the deployed site, then changes asked by the owner.

| Change | Commit |
|---|---|
| Webhook and order page settled one payment twice and refunded it: row lock + re-read | `b47c2b8` |
| Google Sheets clear call sent `[]` instead of `{}`: fixed, client unit-tested | `56f197f` |
| Home search filters narrow each other (API lists each show's months) | `9e15505` |
| Sign-in form in the design system | `6405661` |
| Poster upload, stored in MySQL (decision 14) | `2ecc37d` |
| Date pickers in the design system | `ab5074d` |
| Box-office staff account created in Clerk | `49d0f11` |
| Phone menu closes on every link; Studio in the header | `2bf21be` |
| Hall plan: seats switched on and off (decision 15) | `2c53927` |
| Docs rewritten: diagrams, short decisions, challenge answers split out | this commit |

Each bug was reproduced by a failing test before the fix.
