=== And-MenuManager ===
Contributors: galandras12
Tags: menu, navigation, menük, navigáció, performance
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.5.1
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

= 0.5.1 =
* Javítva: a menüfa visszaugrott a tetejére, valahányszor rákattintottál egy menüpontra vagy mentettél. A kijelölés mostantól nem építi újra a fát, a többi művelet pedig megőrzi a görgetési pozíciót.

= 0.5 =
* Javítva: a „Tartalom hozzáadása” panelről felvett oldal a menü végére, gyökérszintre került ahelyett, hogy a saját ága alá kerülne. Mostantól a menüben már szereplő legközelebbi ős alá kerül.
* A hozzáadás után a felület odagörget az új menüponthoz és megvillantja, az értesítés pedig kiírja, melyik menüpont alá került.
* Új: „Hiányzó aloldalak pótlása” gomb menünként és minden menüre – a tételesen tárolt (pl. WordPressből átemelt) menükben felveszi a hiányzó aloldalakat a megfelelő menüpont alá.

= 0.4 =
* Javítva: a menük átemelése nagy menüknél elakadhatott, mert minden egyes menüpont után külön adatbázis-írás és teljes gyorsítótár-ürítés futott. A kötegelt műveletek most egyszer, a végén ürítenek.
* Javítva: hiba esetén üres piros sáv jelenhetett meg üzenet nélkül. A hibaszöveg mostantól hálózati hibából és időtúllépésből is előáll.
* Új: futó művelet közben élőben látszik a menük és menüpontok száma, nem kell frissíteni az oldalt.
* Új: ha a kérés megszakad, de a szerver tovább dolgozik, a felület tovább követi a folyamatot, és annak végén magától frissül.
* Új: „Aloldalak szinkronizálása” gomb menünként és minden menüre, választható mélységgel – a WordPress menüből kimaradt aloldalakat pótolja.
* Új: hibanapló időbélyeggel a Beállítások oldal végén, .txt exporttal. A „Gyorsítótár ürítése” gomb ezt is kiüríti.

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
