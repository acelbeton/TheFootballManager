# Leegyszerűsített adatbázis

## Okok
+ Az előzőnek kitalált adatbázis túl komplex és robosztus egy szakdolgozathoz
+ Egyszerűbb adatbázisra van szükségem, amellyel a core gameplay-t betudom mutatni

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
    - `position` &rarr; Játékos pozíciója
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

8. #### Standing tábla
    - `id`
    - `league_id` &rarr; A liga id-ja
    - `team_id` &rarr; A csapat id-ja
    - `goals_scored` &rarr; Mennyi gólt lőtt a csapat
    - `goals_conceded` &rarr; Mennyi gólt kapott a csapat
    - `points` &rarr; Szerzett pontok
    - `matches_played` &rarr; Lejátszott meccsek
    - `matches_won` &rarr; Nyert meccsek
    - `matches_drawn` &rarr; Döntetlenek
    - `matches_lost` &rarr; Vesztett meccsek

9. #### PlayerPerformance tábla
    - `id`
    - `player_id` &rarr; Játékos id-ja
    - `match_id` &rarr; Meccs id-ja
    - `goals_scored` &rarr; Lőtt gólok
    - `assists` &rarr; Gólpasszok száma
    - `rating` &rarr; A játékos értékelése
    - `minutes_played` &rarr; Játszott percek

