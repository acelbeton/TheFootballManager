# Roadmap a fejlesztéshez

## Fejlesztési sorrend

1. ### Adatbázis megtervezése
   - #### Milyen táblákra lesz szükségem?
     - Lásd Database.md
     - Indexelés a hatékonyság növeléséhez
   - #### Modellek megírása
     - Scope-ok megírása
     - Kapcsolatok megírása
     - Függvények megírása

2. ### Backend megírása
   - #### Felhasználó hitelesítés megírása
   - #### Controllerek megírása, lekérdezése összerakása
     - Alapműveletek (CRUD) megírása, kigondolni közben milyen többi lekérdésre lenne szükség
   - #### API-k felépítése
     - Statok lekérése
     - Profil
     - Csapat létrehozás
     - Játékos adatok lekérése
     - Csapatok statisztikái
   - #### Websocket (Laravel Reverb)
     - A piacra egy valós idejű frissítés
     - Meccsek közvetítése élőben
     - (Opcionális) Eredmények és fontosabb értesítések
   
3. ### Frontend fejlesztés
   - #### Livewire dokumentáció átnézése &rarr; Alap dolgokat tudni
   - #### UI tervezése
     - Alap színek:
       * Fő szín: #003366 &rarr; Header, gombok, sidebar
       * Sportos szín: #28A745 &rarr; Highlight-ok, siker üzenetek
       * Háttér szín: #FFFFFF
     - Másodlagos színek:
       * Háttérszín szekcióknak: #F4F4F4
       * Halvány szín: #555555 &rarr; Szövegszín, borderszín, másodlagos elemek
       * Premium szín (nem hiszem hogy használom): #FFD700 &rarr; Speciális dolgokra
     - Interaktív színek:
       * Hiba/Figyelmeztetés: #DC3545 &rarr; Alertek, túl lett licitálva a játékos jelzés
       * Információs: #17A2B8 &rarr; Hintek, Meccs kommentálásnál szövegszín maybe
       * Kikapcsolt elemek jelzése: #A0A0A0
   - #### Milyen view-k kellenek?
     - Frontpage &rarr; **TBA**
     - Login/Register
     - Team creation oldal &rarr; Mesterségesen generált játékos keret 15 fő, alap formation 4-4-2 lesz
     - MyTeam oldal &rarr; Csapat taktika állítása, **training(?)** indítása, **formáció(?)** állítása, Focista infót itt lehet megnézni &rarr; Rákkattintasz az ikonjára és modal jön elő
     - Market oldal &rarr; Itt lehet majd minden piaccal kapcsolatos dolgot nézni &rarr; Szűrés opciók, Itt is modallal lehet specifikus árveréseket nézni **(Ez változhat!)**
     - Standing oldal &rarr; Minden statisztikát itt lehet megnézni, tabella itt lesz
     - Schedule oldal &rarr; Lehet valamelyik másik view-ba lesz téve &rarr; Itt lehet nyomon követni naptár formában a meccseket, trainingeket stb. (**TODO** Ki kéne találni, hogy a bemutatásnál hogyan kéne a rendszert bemutatni!) 
     - User Profile oldal &rarr; User infókkal
     