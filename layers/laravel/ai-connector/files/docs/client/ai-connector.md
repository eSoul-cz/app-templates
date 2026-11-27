# Jak si připojit Claude nebo ChatGPT k aplikaci {{název projektu}}

S připojeným AI asistentem můžete pracovat s obsahem aplikace běžnou řečí, například:

- „Které záznamy jsem v poslední době měnil(a)?“
- „Najdi a shrň, co v aplikaci máme k tématu X.“
- {{příklad úkolu specifický pro projekt}}

## Co budete potřebovat

- svůj účet do administrace aplikace,
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
| zjistit, pod kterým uživatelem pracuje a jaká oprávnění připojení má | měnit kód, šablony, nastavení nebo nasazení aplikace |
| {{co asistent v tomto projektu může}} | spravovat uživatele, role a oprávnění |
| | trvale mazat data |
| | dělat cokoli, k čemu nemáte oprávnění vy |

Asistent pracuje pod vaším účtem a má jen vaše oprávnění. Každou jeho akci aplikace zaznamená (kdo, který asistent, jaký nástroj, kdy a s jakým výsledkem; hesla a tajné hodnoty se do záznamu neukládají).

## Odpojení

Přístup zrušíte v administraci v sekci **Připojení asistenti**, nebo odebráním konektoru v Claude či ChatGPT. Odpojení platí okamžitě.

## Kam putují data

Obsah, který asistent přečte, odchází k vašemu poskytovateli AI (Anthropic, OpenAI) a řídí se vašimi podmínkami u něj. Asistent vidí jen to, co v administraci vidíte vy.
{{doplnění projektu: jaké osobní údaje nástroje vracejí a kdo je správcem těchto údajů}}
