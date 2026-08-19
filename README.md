# And-MenuManager

WordPress admin plugin **több száz / több ezer oldalas** oldalstruktúrák navigációs menüinek kezelésére.

A beépített WordPress menükezelő minden menüpontot külön bejegyzésként (`nav_menu_item`) tárol, és a szerkesztőben egyszerre tölti be az összes oldalt. Néhány száz aloldal fölött ez belassul, lefagy, vagy időtúllépéssel elszáll. Az And-MenuManager ezt a problémát az alapoknál oldja meg.

---

## Mitől gyors?

| | Beépített WordPress menü | And-MenuManager |
|---|---|---|
| Tárolás | minden menüpont = 1 bejegyzés + 8-10 metaadat | menünként néhány **szabály-elem** |
| 800 aloldal a menüben | ~800 bejegyzés + ~7000 meta sor | **1 sor** (egy szabály: „ez az oldal + minden aloldala”) |
| Szerkesztő betöltése | az összes oldal egyszerre, teljes objektumként | csak a szabály-elemek, az aloldalak igény szerint, lapozva |
| Új aloldal | kézzel hozzá kell adni | **magától megjelenik** |
| Oldallista lekérdezése | teljes `WP_Post` objektumok | egyetlen szűk oszloplistás lekérdezés, gyorsítótárazva |

Három tervezési döntés adja a sebességet:

1. **Szabályalapú menü.** A menü nem tárolja az aloldalakat, hanem egy szabályt tárol róluk. A tényleges fa megjelenítéskor áll össze, és gyorsítótárba kerül.
2. **Oldalhierarchia-index.** Egyetlen lekérdezés hozza az összes oldal azonosítóját, szülőjét, címét és slugját (nem teljes bejegyzés-objektumokat), ebből épül a fa. Ez memóriában marad, így a permalinkek előállítása sem indít lekérdezéseket. Nagyon nagy oldalszám fölött (alapból 25 000) automatikusan „közvetlen” módra vált, ahol csak az éppen kért szint kerül lekérdezésre.
3. **A felület soha nem rajzol ki több ezer sort.** A fában csak a szabály-elemek látszanak; az aloldalak kinyitásra, lapozva töltődnek be.

---

## Funkciók

### Menükezelés
- Korlátlan számú menü, kereséssel és lapozással
- Drag & drop rendezés tetszőleges mélységben, körkörös hivatkozás elleni védelemmel
- Billentyűzetes mozgatás is (↑ ↓ → ←) az akadálymentességért
- Oldal, bejegyzés, egyedi link, archívum és nem kattintható címsor mint menüelem
- Menü másolása, exportálás / importálás JSON-ben

### Automatizmusok
- **Aloldalak automatikus hozzáadása** – bekapcsolható elemenként; az új aloldalak azonnal megjelennek, kézi beavatkozás nélkül
- **Automatikus mélységkorlát** elemenként és menü szinten
- **Automatikus rendezés**: oldal sorrend (`menu_order`), cím, slug vagy dátum szerint
- **Új gyökér-oldal automatikus felvétele** a kiválasztott menü(k)be
- **Árva elemek takarítása**: törölt oldal menüpontja magától eltűnik
- **Élő címek**: az oldal átnevezésekor a menü is frissül (kivéve, ahol felülírtad a címkét)
- **Időzített megjelenés**: menüpontonként kezdő és záró időpont
- **Szerepkör alapú láthatóság**: menüpont csak bejelentkezve / kijelentkezve / adott szerepköröknek
- **Napi karbantartás**: állapotjelentés és gyorsítótár-előmelegítés cronból

### Szülő oldalak és kizárások
- Bármely oldal felvehető menügyökérként (a „Tartalom hozzáadása” panelről, kereséssel vagy lefúrással)
- Az automatikusan megjelenő aloldalak egyenként **elrejthetők** (kizárás) és visszakapcsolhatók
- Az automatikus aloldal **rögzíthető** külön menüelemként, ha át kell nevezni vagy sorba rendezni
- A menü élő előnézete egy kattintással

### Beillesztés
| Mód | Használat |
|---|---|
| Sablonpozíció | Beállítások → Sablonpozíciók: a téma menühelyére (pl. felső navigáció) rendelve, **a téma módosítása nélkül** |
| Blokk | „And-MenuManager menü” blokk a szerkesztőben (élő előnézettel) |
| Widget | „And-MenuManager menü” widget az oldalsávba |
| Shortcode | `[amm_menu id="fomenu" style="horizontal" depth="2"]` |
| Sablonfüggvény | `<?php amm_menu( 'fomenu' ); ?>` |

Megjelenési módok: függőleges, lenyíló (harmonika), vízszintes (lenyíló almenükkel), oszlopos (nagy menükhöz).

### Hozzáférés
Két saját jogosultság, szerepkörönként külön bekapcsolható a Beállítások → Hozzáférés mátrixban:

- `amm_manage_menus` – menük szerkesztése
- `amm_manage_settings` – beállítások és hozzáférés kezelése

Az adminisztrátor jogosultsága nem vehető el, így nem lehet kizárni magad a felületről.

---

## Telepítés

1. Másold a plugin mappáját a `wp-content/plugins/and-menumanager` könyvtárba.
2. Aktiváld a WordPress bővítménykezelőjében.
3. Nyisd meg a bal oldali **Menükezelő** menüpontot.

Aktiváláskor létrejön két saját tábla (`{prefix}amm_menus`, `{prefix}amm_items`), és az adminisztrátor megkapja a jogosultságokat.

### Átállás a meglévő menükről
A **Menükezelő → WordPress menük átemelése** gomb a beépített menüket másolatként áthozza, a sablonpozíció-hozzárendelésekkel együtt. Az eredeti WordPress menük érintetlenek maradnak, így bármikor visszaállhatsz.

---

## Első lépések nagy oldalszámnál

1. Hozz létre egy menüt (**Új menü**).
2. A jobb oldali **Tartalom hozzáadása** panelen keresd meg a fő szülő oldalt, és add hozzá (`+`).
3. A fában az elem alatt megjelenik az *„Aloldalak automatikusan”* jelölés a darabszámmal – ez egyetlen szabály, nem több száz sor.
4. Az **Aloldalak kezelése** gombbal nyisd ki a listát, és rejtsd el, amit nem szeretnél megjeleníteni.
5. A **Menü** fülön állítsd be a megjelenést és a mélységkorlátot, majd a **Beállítások → Sablonpozíciók** alatt rendeld a téma menühelyéhez.

---

## WP-CLI

```bash
wp amm list-menus        # menük és méretük
wp amm flush             # gyorsítótár ürítése
wp amm prewarm           # oldalindex előmelegítése
wp amm import-core       # beépített WordPress menük átemelése
wp amm purge-orphans     # árva menüelemek törlése
wp amm export --file=menuk.json
```

---

## Fejlesztőknek

### Sablonfüggvények
```php
amm_menu( 'fomenu', array( 'style' => 'horizontal', 'depth' => 2 ) ); // kiírja
$html = amm_get_menu( 'fomenu' );                                     // visszaadja
$tree = amm_get_menu_tree( 'fomenu' );                                // nyers fa tömbként
amm_menu_exists( 'fomenu' );
```

### Hookok
```php
apply_filters( 'amm_resolved_tree', $tree, $menu );        // a feloldott fa
apply_filters( 'amm_menu_html', $html, $menu, $args );     // a kész HTML
apply_filters( 'amm_item_visible', $visible, $item );      // egyedi láthatósági szabály
apply_filters( 'amm_post_statuses', $statuses );           // figyelembe vett státuszok
apply_filters( 'amm_cache_context_parts', $parts );        // gyorsítótár-kulcs (pl. többnyelvűséghez)

do_action( 'amm_menu_created', $menu_id, $data );
do_action( 'amm_menu_updated', $menu_id, $data );
do_action( 'amm_menu_deleted', $menu_id );
do_action( 'amm_auto_added_item', $menu_id, $post_id );
```

### REST API
Névtér: `/wp-json/and-menumanager/v1` – `menus`, `menus/{id}/tree`, `menus/{id}/items`, `menus/{id}/reorder`, `menus/{id}/exclusions`, `objects`, `settings`, `roles`, `tools/{action}`, `health`, `export`, `import`.

### Felépítés
```
and-menumanager.php          bootstrap, autoloader
includes/
  class-amm-plugin.php       komponensek összefűzése
  class-amm-installer.php    táblák, verziókezelés
  class-amm-pages.php        oldalhierarchia-index (a sebesség lelke)
  class-amm-tree.php         szabályok feloldása fává
  class-amm-renderer.php     HTML kimenet
  class-amm-cache.php        verziószámos gyorsítótár
  class-amm-automations.php  tartalomváltozásra reagáló automatizmusok
  class-amm-rest.php         REST végpontok
  class-amm-admin.php        admin felület betöltése
  class-amm-importer.php     import / export / átállás
  class-amm-cli.php          WP-CLI parancsok
assets/                      admin és látogatói oldali CSS + JS (build lépés nélkül)
blocks/menu/                 Gutenberg blokk
```

---

## Gyorsítótár

Minden menü feloldott fája gyorsítótárba kerül (objektum-cache, ha van; egyébként transient). Az érvénytelenítés verziószám-léptetéssel történik, így soha nem kell több ezer kulcsot törölni. A cache automatikusan ürül, ha egy oldal címe, slugja, szülője, sorrendje vagy státusza megváltozik – más mentések nem indítanak felesleges újraépítést.

## Verziók

### 0.5.2
- **Javítva: a menü kétszer jelent meg a látogatói oldalon**, ha sablonpozícióhoz (pl. „Elsődleges menü”) volt rendelve. A `pre_wp_nav_menu` szűrő kiírta a menüt *és* vissza is adta, a WordPress pedig a visszaadott értéket még egyszer kiírta. A szűrő mostantól csak visszaad — a kiírás a WordPress dolga.
- A téma `menu_id` értéke rákerül a `<ul>` elemre, így a témára írt CSS (pl. `#primary-menu`) továbbra is illeszkedik.
- Ha a téma kifejezetten konténer nélkül kéri a menüt (`container => false`), a plugin sem tesz köré sajátot — így nem esik szét a téma elrendezése.

### 0.5.1
- **Javítva: a fa visszaugrott a tetejére.** Egy menüpontra kattintva a felület újraépítette a teljes fát, így több száz elemnél minden kijelölés után vissza kellett görgetni. A kijelölés most már csak az érintett sorok jelölését és az oldalsó panelt frissíti, a fa DOM-ja marad; a többi újrarajzolás (mentés, hozzáadás, törlés, ki- és becsukás) pedig megőrzi a görgetési pozíciót a fában, a menülistában és a választóban is.

### 0.5
- **Javítva: a hozzáadott oldal rossz helyre került.** A „Tartalom hozzáadása” panelről felvett oldal `parent_id = 0` értékkel mentődött, vagyis a menü **gyökérszintjének a végére** – egy több száz elemes menüben ez gyakorlatilag megtalálhatatlan. Mostantól a plugin felfelé haladva megkeresi az oldal legközelebbi olyan ősét, ami már szerepel a menüben, és az alá teszi.
- **Odagörgetés és kiemelés.** Hozzáadás után a felület a fában odaugrik az új menüponthoz, megvillantja, és az értesítés is kiírja, melyik menüpont alá került.
- **Új: „Hiányzó aloldalak pótlása”.** Menünként (Menü fül) és minden menüre (Beállítások → Karbantartás). Végigjárja a menü oldalra mutató elemeit, és minden hiányzó aloldalt felvesz alájuk valódi menüpontként, a kizárásokat tiszteletben tartva. Ez a tételesen tárolt, WordPressből átemelt menükhöz való.

  A **„pótlás”** és a **„szinkronizálás”** két út ugyanahhoz a célhoz: a pótlás valódi menüpontokat hoz létre (a meglévő, tételes szerkezetbe illeszkedve), a szinkronizálás pedig szabályt kapcsol be, ami az összes – és minden ezután létrehozott – aloldalt magától megjeleníti, sorok létrehozása nélkül.

### 0.4
- **Javítva: az átemelés elakadása nagy menüknél.** Minden egyes menüpont beszúrása után lefutott egy külön adatbázis-írás *és* egy teljes gyorsítótár-ürítés (opció-írással). Ezer menüpontnál ez ezer fölösleges írás volt, ami időtúllépésbe vihette a kérést – miközben a szerver a háttérben tovább dolgozott. A kötegelt műveletek (átemelés, tömeges hozzáadás, aloldal-szinkron) mostantól felfüggesztik a gyorsítótárat, és egyszer, a végén ürítenek.
- **Javítva: üres hibasáv.** Hálózati hiba vagy időtúllépés esetén a hibaobjektum gyakran üres, ezért a piros sáv szöveg nélkül jelent meg. A hibaszöveg most több forrásból áll össze (üzenet, hibakód, HTTP állapot), és soha nem üres.
- **Élő állapotkövetés.** Hosszú művelet közben a folyamatsáv 3 másodpercenként mutatja az aktuális menü- és menüpontszámot, a bal oldali lista elemszámai pedig a felület újraépítése nélkül frissülnek – nem kell újratölteni az oldalt.
- **Követés megszakadt kérés után.** Ha a kérés elhal, de a szerver tovább dolgozik, a felület figyeli a menüpontok számát; amikor az három körön át nem változik, lezárja a folyamatot és frissít. A követés kézzel is leállítható.
- **Aloldalak szinkronizálása.** Új gomb menünként (Menü fül) és az összes menüre (Beállítások → Karbantartás), választható mélységgel. Minden olyan menüpontnál bekapcsolja az automatikus aloldal-kezelést, aminek van aloldala – így a WordPress menüből kimaradt aloldalak is megjelennek, új menüpontok létrehozása nélkül.
- **Hibanapló.** A Beállítások oldal végén időbélyeggel gyűjti a szerver- és felületoldali hibákat (legfeljebb 200 bejegyzés), `.txt` fájlba exportálható. A „Gyorsítótár ürítése” gomb a naplót is kiüríti.

### 0.3
- **A „WordPress menük átemelése” újrafuttatható, és nem duplikál.** A korábban átemelt menüket megkeresi (elmentett eredet, majd slug és név szerint – így a 0.3 előtt átemelt menük is felismerhetők), összeveti a WordPress menü tartalmával, és **csak a hiányzó menüpontokat pótolja**. A kézzel hozzáadott elemeket érintetlenül hagyja, csak jelenti őket. A művelet végén összefoglalót ad: hány menü jött létre, hány lett újraellenőrizve, hány menüpont pótlódott.
- A sablonpozíciót az újraellenőrzés csak akkor tölti ki, ha az még üres – a szándékosan másra állított pozíciót nem írja felül.
- **Azonos nevű menü létrehozásakor buborék kérdez rá:** „Ezen a néven már van egy menü… Szeretnél létrehozni még egyet ugyanezen a néven?” Az ellenőrzés a szerveren fut, így a szűrt vagy lapozott lista sem téveszti meg. Ha az ellenőrzés nem fut le, nem blokkolja a munkát.
- A hosszabb műveletek futás közben **pörgő ikont** mutatnak a megnyomott gombon (a felirat is átvált, pl. „Átemelés folyamatban…”), és a felület tetején egy folyamatjelző sáv is megjelenik. Érintett műveletek: WordPress menük átemelése, gyorsítótár ürítése, oldalindex előmelegítése, árva elemek törlése, kijelölt oldalak tömeges hozzáadása.
- A futó művelet gombja a befejezésig letiltva marad, így egy kattintással nem indítható el kétszer.
- A jelzés `aria-busy` és `aria-live` attribútumokkal képernyőolvasóval is követhető, és tiszteletben tartja a csökkentett mozgás (`prefers-reduced-motion`) beállítást.

### 0.2
- **Javítva:** a REST hívások sima (nem „szép”) permalink-beállítás mellett elromlottak. A REST gyökér ilyenkor maga is lekérdezés-paraméter (`?rest_route=…`), így a hozzáfűzött paraméterek egy második `?`-et eredményeztek, és az útvonal érvénytelen lett. Emiatt az új menü létrejött ugyan az adatbázisban, de a lista sosem töltődött be, így nem jelent meg. A felület mostantól a `wp.apiFetch`-et használja, ami mindkét URL-formát kezeli.
- Az **Új menü** létrehozása a felületbe épített beviteli mezővel történik, felugró böngészőablak helyett (Enter is elküldi, Esc bezárja).
- Menü a **Beállítások** oldalról is létrehozható.
- A hibák tartós hibasávban jelennek meg a felület tetején, nem csak néhány másodpercre felvillanó értesítésben, és a szerver hibaüzenete is látszik.
- **Önjavító adatbázis-ellenőrzés:** ha a táblák hiányoznak (pl. félbemaradt aktiválás miatt), a plugin újra létrehozza őket. A Beállítások → Állapot panel kiírja a táblák, a séma és a plugin állapotát.

### 0.1
- Első kiadás.

## Követelmények

- WordPress 5.8+
- PHP 7.4+

## Licenc

GPL-2.0-or-later
