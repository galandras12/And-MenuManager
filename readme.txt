=== And-MenuManager ===
Contributors: galandras12
Tags: menu, navigation, menük, navigáció, performance
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gyors, stabil menükezelés több száz / több ezer oldalas WordPress oldalakhoz. Szabályalapú menük, automatikus aloldal-hozzáadás, drag & drop, szerepkör alapú hozzáférés.

== Description ==

A beépített WordPress menükezelő minden menüpontot külön bejegyzésként tárol, és a szerkesztőben egyszerre tölti be az összes oldalt. Néhány száz aloldal fölött ez belassul vagy lefagy.

Az And-MenuManager a menüt szabályként tárolja: egyetlen elem jelentheti azt, hogy „ez az oldal és az összes aloldala”. A tényleges fa megjelenítéskor áll össze egy gyorsítótárazott oldalhierarchia-indexből, a szerkesztő pedig soha nem rajzol ki több ezer sort.

Főbb funkciók:

* Aloldalak automatikus hozzáadása – az újonnan létrehozottak is azonnal megjelennek
* Bármely szülő oldal kiválasztható, az aloldalak egyenként elrejthetők
* Drag & drop rendezés és billentyűzetes mozgatás
* Beillesztés sablonpozícióba, blokként, widgetként vagy shortcode-dal
* Szerepkör alapú hozzáférés az adminisztrátorokon túl
* Időzített és szerepkörhöz kötött menüpont-láthatóság
* Árva elemek takarítása, állapotjelentés, import / export, WP-CLI parancsok
* Meglévő WordPress menük egykattintásos átemelése

== Installation ==

1. Töltsd fel a plugin mappáját a `/wp-content/plugins/` könyvtárba.
2. Aktiváld a bővítményt a WordPress admin felületén.
3. Nyisd meg a bal oldali „Menükezelő” menüpontot.

== Frequently Asked Questions ==

= Elveszítem a meglévő menüimet? =

Nem. A „WordPress menük átemelése” gomb másolatot készít, az eredeti menük érintetlenek maradnak.

= Mi történik, ha új aloldalt hozok létre? =

Ha a szülő oldalon be van kapcsolva az automatikus aloldal-kezelés, az új oldal magától megjelenik a menüben.

= Hány oldalt bír el? =

Az alapértelmezett beállítás 25 000 oldalig tart teljes indexet memóriában; e fölött automatikusan szintenkénti lekérdezésre vált. A küszöb a beállításokban módosítható.

== Changelog ==

= 0.3 =
* A „WordPress menük átemelése” újrafuttatható: a már átemelt menüket megkeresi és összeveti a WordPress menüvel, csak a hiányzó menüpontokat pótolja – nem hoz létre másolatokat. A kézzel hozzáadott elemeket nem bántja.
* Azonos nevű menü létrehozásakor buborék kérdez rá, hogy tényleg kell-e még egy ugyanilyen nevű menü.
* A hosszabb műveletek (WordPress menük átemelése, gyorsítótár ürítése, indexépítés, árva elemek törlése, tömeges hozzáadás) futás közben pörgő ikont mutatnak a gombon, és a felület tetején is látszik, hogy folyamatban vannak.
* A futó művelet gombja a befejezésig letiltva marad, így nem indítható kétszer.

= 0.2 =
* Javítva: a REST hívások sima (nem "szép") permalink-beállítás mellett elromlottak, ezért az újonnan létrehozott menü nem jelent meg a listában.
* Az „Új menü” létrehozása mostantól a felületbe épített beviteli mezővel történik, felugró böngészőablak helyett.
* A menük a Beállítások oldalról is létrehozhatók.
* A hibák tartós hibasávban jelennek meg, nem csak eltűnő értesítésben.
* Önjavító adatbázis-ellenőrzés és diagnosztikai panel a Beállítások → Állapot alatt.

= 0.1 =
* Első kiadás.
