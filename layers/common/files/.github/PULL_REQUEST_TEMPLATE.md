## What changes

<!-- Two to five sentences. What the PR does and why, not a list of files. -->

Closes #

- **Freelo:** <!-- URL of the paired task, https://app.freelo.io/task/… -->
- **ADR:** — <!-- or docs/adr/NNNN-… -->

## How to verify

<!-- Steps for the reviewer: concrete URL, role of the signed-in user, test data. -->

## Definition of Done

- [ ] The code follows the rules in `docs/project_guidelines/`.
- [ ] The checks listed in `docs/project_guidelines/tools.md` passed locally on the code being pushed.
      Checks run and their results: <!-- one line per check, or `Bez testu: <reason>` -->
- [ ] New or changed behaviour has a test; a bug fix has a regression test that fails without the fix.
- [ ] Mutation testing was run: every escaped mutant on changed lines is killed or listed under
      Risks as accepted, with a reason. <!-- Delete this line when it was not run. -->
- [ ] Domain terms match `GLOSSARY.md`; a new term is added in this PR.
- [ ] A change to the architecture or an external contract has an ADR in this PR, or links an
      existing `accepted` one.
- [ ] No new dependency, or the maintainer's approval is stated here:
- [ ] The Freelo task has its time reported and a state that matches reality.

## Risks and what I did not do

<!-- Known limits, deferred work (link the follow-up issue), migration impact, accepted mutants.
     An empty answer is an answer too: write "none". -->
