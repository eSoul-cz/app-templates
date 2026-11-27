# Jak si připojit Claude nebo ChatGPT k webu {{název projektu}}

S připojeným AI asistentem můžete psát a upravovat stránky webu běžnou řečí, například:

- „Vytvoř návrh stránky Služby: nadpis, tři odstavce a obrázek, který ti pošlu.“
- „Zkrať úvodní text na stránce O nás a oprav překlepy.“
- „Které stránky mají změny, které ještě nejsou na webu?“
- „Vrať stránku Kontakt do podoby z minulého týdne.“

Asistent nikdy nic nezveřejní. Všechny jeho změny zůstanou v administraci jako **nová verze stránky**, vy je zkontrolujete a web se změní až ve chvíli, kdy sami stisknete **Publikovat web**.

## Co budete potřebovat

- svůj účet do administrace webu,
- adresu konektoru: **`https://{{doména administrace}}/mcp`**,
- Claude (claude.ai nebo aplikace Claude) nebo ChatGPT. Vlastní konektory nemusí být dostupné ve všech tarifech, aktuální podmínky najdete u poskytovatele.

## Claude

1. Otevřete **Nastavení → Konektory** a zvolte **Přidat vlastní konektor**.
2. Zadejte název (např. „{{název projektu}}“) a adresu konektoru.
3. Klikněte na **Připojit**. Otevře se přihlašovací stránka administrace: přihlaste se a potvrďte přístup.
4. V novém rozhovoru konektor zapněte v nabídce nástrojů.

Ve firemním účtu (Team, Enterprise) přidává konektor správce organizace.

## ChatGPT

1. Otevřete **Nastavení → Aplikace a konektory** a vytvořte nový konektor.
2. Zadejte adresu konektoru a jako způsob přihlášení zvolte OAuth.
3. Přihlaste se do administrace a potvrďte přístup.

## Co asistent může a nemůže

| Může | Nemůže |
|---|---|
| zjistit, pod kterým uživatelem pracuje a jaká oprávnění připojení má | **zveřejnit** web ani stránku: web se změní až po vašem stisku „Publikovat web“ |
| vypsat stránky (název, adresa, zveřejněná, kdy se měnila, zda má nezveřejněné změny) a přečíst obsah stránky | stránku **smazat** ani trvale odstranit jakákoli data |
| zjistit, z jakých druhů bloků lze stránku složit (nadpis, text, obrázek) a jaká mají pravidla | zveřejnit nebo skrýt stránku, nastavit úvodní stránku nebo změnit adresu už zveřejněné stránky |
| vytvořit novou stránku, vždy jako **nezveřejněný návrh** | měnit nastavení webu, formuláře ani přečíst odeslané zprávy z formulářů |
| upravit nadpis, popis, sociální obrázek a bloky stránky (a adresu nezveřejněné stránky) | spravovat uživatele, role a oprávnění |
| nahrát obrázek (PNG, JPG, WebP nebo AVIF, nejvýše 10 MB, nejvýše 8000 × 8000 px; jen soubor, který jí pošlete, ne z internetu) | měnit kód, šablony nebo nasazení aplikace |
| vypsat dřívější verze stránky a jednu vrátit zpět jako novou verzi | smazanou stránku obnovit ani dělat cokoli, k čemu nemáte oprávnění vy |

Asistent pracuje pod vaším účtem a má jen vaše oprávnění. Každá jeho změna se uloží jako **verze stránky** označená „AI asistent“ (v historii stránky a v přehledu změn na stránce **Publikovat web**), takže vždy vidíte, co se změnilo, a můžete se vrátit. Každou jeho akci aplikace zároveň zaznamená (kdo, který asistent, jaký nástroj, kdy a s jakým výsledkem; hesla a tajné hodnoty se do záznamu neukládají).

## Jak vypadá práce

1. Požádejte asistenta o změnu. Vytvoří návrh nebo uloží novou verzi stránky.
2. V administraci otevřete **Stránky**, zkontrolujte výsledek a případně ho upravte.
3. Až jste spokojeni, stiskněte **Publikovat web**. Teprve tehdy se změna objeví na veřejném webu.

## Odpojení

Přístup zrušíte v administraci v sekci **Připojení asistenti**, nebo odebráním konektoru v Claude či ChatGPT. Odpojení platí okamžitě.

## Kam putují data

Obsah stránek, který asistent přečte, a obrázky, které mu pošlete, odchází k vašemu poskytovateli AI (Anthropic, OpenAI) a řídí se vašimi podmínkami u něj. Asistent vidí jen to, co v administraci vidíte vy. Nástroje nevracejí osobní údaje návštěvníků webu (odeslané zprávy z formulářů asistent nevidí); správcem osobních údajů uživatelů administrace, které asistent zmíní (jméno přihlášeného uživatele), je {{správce údajů}}.
