# Teendők

## 2025.03.01.
- [ ] User profil elkészítése view-val és livewire komponenssel
  * User alapinfók
  * Csapatairól infó &rarr; Név, Statok, Jelenlegi szezonban hanyadikak
  * Liga infó &rarr; Melyikben van, hanyadik hétnél tart
  * Jövőbeli meccsek maybe?
  * Idk még mit lehet
- [x] Design felépítése scss szinten is
  - scss/
    * abstracts/   // Variables, mixins, functions, placeholders
    * base/        // Resets, typography, base styles
    * components/  // UI components (buttons, cards, etc.)
    * layout/      // Grid, header, footer, etc.
    * pages/       // Page-specific styles
    * themes/      // Theme variations
    * vendors/     // Third-party styles
    * main.scss    // Main file importing all partials

- [x] Dashboard, Login, Register design
  * A login és register elkészült, a dashboard még nem &rarr; gondolkodni hogyan nézzen ki

## 2025.03.16.
- [ ] Csapat választó oldal elkészítése
  * Ahol a 3 lehetséges közül lehet választani
  * Itt lehet törölni csapatot &rarr; Átgondolni, mi legyen pontosan ilyenkor?
    * Lehet kéne valami AI ami végigviszi vele a szezont, majd kitöröljük
  * Csapat létrehozás gomb, ha nincs még meg a három
- [x] Melyik csapatot kezeljük jelenleg?
  * Adatbázisba mentjük, ennek a kezelése csapatváltásnál és csapat létrehozásnál
- [ ] Team management oldal elkészítése
  * Keret management, játékosról infó
  * Aktív keret elmentése adatbázisban
  * Formáció cseréje itt
- [ ] Tabella oldal elkészítése
  * A szezonról és a ligáról adatok
  * Elkövetkezendő meccsekről infó
  * Gólokról, asszisztokról infó
  * MLSZ adatbankos oldal példának

## 2025.03.17. - 2025.03.20.
- [ ] Training oldal elkészítése
  * Kiválasztani hogy ma training csapatszinten, játékosokra egyénileg
  * Algoritmus elkészítése hozzá

## 2025.03.18.
- [ ] Aukció (Market) oldal elkészítése
  * Oldal elkészítése
  * Kitalálni hogyan működjön: felhasználó csinálhat aukciót vagy csak a rendszer készít
  * Websocket bevezetése, hogy elő legyen a piac

## 2025.03.19.
- [ ] Meccs rendszer létrehozása
  * Algoritmus a meccs eseményekhez
  * Websocket használata itt is maybe(?) &rarr; Átgondolni itt a rendszert

- [ ] Kondíció rendszer implementálása
  * Hogyan tud romlani egy játékos kondíciója, és hogyan javulni?

## 2025.03.08.
- [ ] Dashboard layout elkészítése
