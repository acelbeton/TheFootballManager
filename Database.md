# Adatbázis felépítése

- Milyen táblákra, és azon belül oszlopokra lesz szükségem? 
- Miket szeretnék tárolni?

## Táblák 

1. #### User tábla
   - `id` 
   - `username`
   - `email`
   - `password`
   - `timestamp`

2. #### Team tábla
   - `id`
   - `name` &rarr; Csapat neve
   - `user_id` &rarr; Ezzel csatoljuk a felhasználóhoz a csapatot
   - `current_tactic` &rarr; Jelenlegi taktika
   - `team_quality` &rarr; A csapat minőségi szintje 1-100 -> Csapat Játékosainak rating-jéből átlag 
   - `budget` &rarr; A csapat költségvetése

3. #### Player tábla
   - `id`
   - `name` &rarr; Játékos neve
   - `rating` &rarr; Játékos ratingje &rarr; Statistic-ekből jön össze (statok átlaga)
   - `team_id` &rarr; Melyik csapat tagja
   - `position_id` &rarr; Játékos pozíciója
   - `market_value` &rarr; Piaci értéke &rarr; Teljesítmény alapján változik (MAYBE)
   - `is_on_market` &rarr; Elérhető-e a piacon
   - `condition` &rarr; Játékos kondíciója &rarr; Játszott percek, sérülés alapján, edzés változik
   - `is_injured` &rarr; Sérült-e a játékos

4. #### Match tábla
   - `id`
   - `home_team_id` &rarr; Az hazai csapat id-ja
   - `away_team_id` &rarr; Vendég csapat id-ja
   - `home_team_score` &rarr; Hazai gólszám
   - `away_team_score` &rarr; Vendég gólszám
   - `match_date` &rarr; A meccs időpontja
   - `yellow_cards` &rarr; Sárga lapok
   - `red_cards` &rarr; Piros lapok

5. #### Statistic tábla
   - `id` 
   - `player_id` &rarr; Játékos id-ja
   - `attacking` &rarr; Játékos támadóérzéke 1-100
   - `defending` &rarr; Játékos védekezőérzéke 1-100
   - `stamina` &rarr; Meccsen belüli állóképesség 1-100
   - `technical_skills` &rarr; Technikai képessége a játékosnak 1-100
   - `speed` &rarr; Játékos sebessége 1-100
   - `tactical_sense` &rarr; Játékos taktikai érzéke &rarr; Mennyire képes alkamazkodni a meccs során a változásokhoz 1-100
   
6. #### League tábla
   - `id`
   - `name` &rarr; Liga neve
   - `season` &rarr; A szezon dátuma
   - `prize_money_first` &rarr; A szezon végén első helyezett nyereménye
   - `prize_money_second` &rarr; Második helyezett nyereménye
   - `prize_money_third` &rarr; Harmadik helyezett nyereménye
   - `prize_money_other` &rarr; A többiek nyereménye

7. #### Market tábla
   - `id`
   - `player_id` &rarr; A játékos id-ja aki a piacon megtalálható
   - `current_bid_amount` &rarr; A jelenlegi licitérték
   - `user_id` &rarr; A licitet vezető játékos
   - `timestamps` (created_at, modified_at)

8. #### Training_Session tábla
   - `id`
   - `team_id` &rarr; Az edző csapat id-ja
   - `player_id` &rarr; A játékos id-ja (egyéni tréning)
   - `type` &rarr; Csapat, vagy egyéni edzés-e
   - `stat_improvement` &rarr; A fejlődött statisztika 
   - `date` &rarr; Edzés ideje

9. #### Standing tábla
   - `id`
   - `league_id` &rarr; A liga id-ja
   - `team_id` &rarr; A csapat id-ja
   - `goals_scored` &rarr; Mennyi gólt lőtt a csapat
   - `goals_conceded` &rarr; Mennyi gólt kapott a csapat
   - `points` &rarr; Szerzett pontok
   - `red_cards` &rarr; Mennyi piros lapot kapott a csapat
   - `yellow_cards` &rarr; Mennyi sárgát kapott
   - `matches_played` &rarr; Lejátszott meccsek
   - `matches_won` &rarr; Nyert meccsek
   - `matches_drawn` &rarr; Döntetlenek
   - `matches_lost` &rarr; Vesztett meccsek

10. #### PlayerPerformance tábla
    - `id`
    - `player_id` &rarr; Játékos id-ja
    - `match_id` &rarr; Meccs id-ja
    - `goals_scored` &rarr; Lőtt gólok
    - `assists` &rarr; Gólpasszok száma
    - `rating` &rarr; A játékos értékelése
    - `minutes_played` &rarr; Játszott percek
    - `position_id` &rarr; Pozíciója a meccsen
    - `is_out_of_position` &rarr; Pozíción kívül játszott-e?

11. #### MarketHistory tábla
    - `id`
    - `market_id` &rarr; Piac id-ja &rarr; Melyik licitről van szó
    - `user_id` &rarr; Felhasználó id-ja
    - `bid_amount` &rarr; Mennyit licitált
    - `timestamp`

12. #### Position tábla
    - `id`
    - `name` &rarr; A pozíció neve
    - `description` &rarr; A pozíció leírása

13. #### StandardLineup tábla
    - `id` 
    - `team_id`
    - `formation` &rarr; A formáció típusa pl.: 4-4-2, 3-4-3 stb.

14. #### StandardLineupPlayer tábla
    - `id`
    - `standard_lineup_id` &rarr; Az alap felállás id-ja
    - `player_id` &rarr; Játékos id-ja
    - `position_id` &rarr; A pozíció id-ja, amin játszott
    
15. #### MatchLineup tábla
    - `id`
    - `match_id` &rarr; Meccs id-ja
    - `team_id` &rarr; Csapat id-ja
    - `formation` &rarr; Milyen formációban játszottak pl. 4-4-2, 3-4-3 stb.

16. #### MatchLineupPlayer tábla
    - `id`
    - `match_lineup_id` &rarr; A specifikus meccs felállás id-ja
    - `player_id` &rarr; Játékos id-ja
    - `position_id` &rarr; A meccsen játszott pozíció id-ja

17. #### Transaction tábla
    - `id`
    - `team_id` &rarr; Csapat id-ja
    - `transaction_type` &rarr; Tranzakció típusa
    - `amount` &rarr; Tranzakció értéke
    - `transaction_date` &rarr; Tranzakció dátuma

18. #### TODO Formation tábla
    - Hogyan lenne érdemes formationt tárolni?

   