# Politika AI v týmu

> Vzor ke cvičení modulu 11. Doplňte za svůj tým. Není to právní výklad; povinnosti
> podle AI Actu a smluv s dodavateli nástrojů ověřte s právníkem.

1. **Nástroje a plány.** Které nástroje a v jakém plánu tým používá (doplnit: nástroj,
   plán, kdo licence spravuje). Jiné nástroje jen po domluvě s tech leadem.
2. **Data: co smí do nástroje.** Kód tohoto repozitáře ano. Produkční data, osobní údaje
   zákazníků, tajemství a obsah `.env.local` ne. Nastavení dodavatele k tréninku na datech
   ověřit podle plánu a zapsat sem.
3. **Oprávnění agentů.** Sdílené nastavení v `.claude/settings.json`: konfigurace kontrol
   a testy, které hlídají mantinely, jen ke čtení, testy z výchozího tagu `mNN-start` hlídá
   hook `.claude/hooks/existujici-testy.php` (nový test agent založit a opravit smí), lokální `.env`
   a dešifrovací klíče nečitelné, `composer require`, `git push`, migrace a výpis tajemství
   jen se schválením. Codex, Cursor a Copilot mají v repozitáři jen hook `make check-changed`,
   konfiguraci kontrol u nich hlídá jen review podle `.github/CODEOWNERS`.
4. **Review a CI před merge.** Každá změna projde `make check` v CI a review člověka.
   Změny v konfiguraci kontrol schvaluje vlastník podle `.github/CODEOWNERS`.
5. **Odpovědnost za kód.** Za změnu odpovídá autor PR, ne nástroj. V popisu PR uvádí,
   co četl řádek po řádku (`.github/pull_request_template.md`).
6. **Licence a původ.** Nové závislosti jen přes `composer require` se schválením,
   `composer audit` v CI. Kód zkopírovaný z odpovědi nástroje se posuzuje jako cizí kód.
7. **Školení a evidence.** Kdo nástroj používá, prošel úvodem k těmto pravidlům
   (doplnit: kdy, kde je záznam).
8. **Datum revize.** Pravidla se procházejí jednou za čtvrt roku. Další revize: (doplnit).

## Metriky (návrh)

| Metrika | Odkud | Proč |
|---|---|---|
| Podíl selhaných nasazení | CI a nasazovací pipeline | ukáže, jestli rychlost nejde na úkor stability |
| Přepracování (rework): změny vrácené z review nebo opravené do 14 dní | historie PR a commitů | odhalí „skoro správný“ kód |
| Poměr nálezů senzorů ku nálezům review | výstup `make check` v CI, komentáře v PR | ukáže, co by šlo převést na senzor |
