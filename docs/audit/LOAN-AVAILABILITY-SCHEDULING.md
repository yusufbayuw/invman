# Loan availability scheduling — correction and safety contract

PR #33, follow-up to the concurrent booking fixes. This change is additive and does **not** deploy or merge to main.

## Correct quantity across a time range

For every inventory master, the displayed and validated availability is:

\`borrowable stock - active maintenance holds - peak simultaneous reservation quantity\`.

A timeline sweep clips each reservation to the requested **half-open** time interval \`[start, end)\`. Example: 5 projectors, 3 booked 09:00-10:00 and 3 booked 11:00-12:00. A new 09:00-12:00 reservation can take 2, because peak concurrency is 3, not 6. If two reservations genuinely overlap, their quantities add during the overlapping minutes.

The item picker uses the same calculation in chunks of 200 inventory masters, and transactional draft submission repeats the calculation using locking reads to prevent concurrent oversubscription. The previous MySQL current-read fix for nested \`EXISTS (activity)\` is kept.

## Physical assets not yet returned

- An **outstanding** checkout/return-request blocks stock through its scheduled end. If it is already overdue and not physically confirmed returned, it blocks **every** later time range.
- Before the scheduled end has passed, a future reservation **after that end** may be submitted. This is a *schedule allocation*, not proof of physical availability. Handover still requires available physical item instances; if a previous loan is not returned, checkout fails safely.
- Room and vehicle policies are unchanged: their outstanding checkouts conservatively block all subsequent bookings until return. Do not infer that the item-only rule applies to them.
- Failed/damaged item instances remain non-borrowable; maintenance safety holds also subtract stock.

This behavior should be communicated clearly in borrower-facing status as part of the remaining browser UAT. Do not promise that future handover is guaranteed solely because a slot is reserved.

## Late drafts

\`saveDraft()\` checks the clock on creation. \`submitDraft()\` now rechecks current start/end under the activity lock; an outdated draft cannot become a submitted reservation by using a stale browser tab or direct API call.

## Still not production-ready

This is a source and automated-test improvement in the PR #33 audit branch. Browser-based end-to-end UAT, real production backup/restore rehearsals, and deployment environment verification remain open. CI passing does not close those gates.
