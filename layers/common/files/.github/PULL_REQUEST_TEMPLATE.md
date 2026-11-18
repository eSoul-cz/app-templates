## Co se mění

<!-- Dvě až pět vět. Co PR dělá a proč, ne výčet souborů. -->

Closes #

- **Freelo:** <!-- URL párového úkolu, https://app.freelo.io/task/… -->

## Jak to ověřit

<!-- Kroky pro reviewera: konkrétní URL, role přihlášeného uživatele, testovací data. -->

## Definition of Done

- [ ] Kód odpovídá pravidlům v `vendor/esoul/guidelines` (nebo `docs/project_guidelines/`).
- [ ] Kontroly prošly lokálně (`composer test`, `composer phpstan`, `composer pint`, `pnpm lint`, `pnpm test`, `pnpm build`).
- [ ] Nová nebo změněná logika má test; oprava chyby má regresní test, který bez opravy padá.
- [ ] Doménové názvosloví odpovídá `CONTEXT.md`; nový pojem je doplněný v tomto PR.
- [ ] Rozhodnutí, které mění architekturu, má ADR.
- [ ] Freelo úkol má vykázaný čas a je ve stavu odpovídajícím realitě.

## Rizika a co jsem neudělal

<!-- Známá omezení, odložené věci, migrační dopady. Prázdné pole je taky odpověď: napiš „žádná“. -->
