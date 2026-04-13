# Shopware 6 + ThemeWare Modern Pro – Plugin-Entwicklung: Leitfaden aus der Praxis

**Webagentur Gaul | webagentur-gaul.de**
Erarbeitet aus der Entwicklung der Plugins **WGShopBooster**, **WGCareInstructions**, **WGSwissMadeMenu** und **WGFaq** für wenk-socken.ch

---

## 1. Das Kernproblem: ThemeWare überschreibt alles

ThemeWare Modern Pro ist kein einfaches Child-Theme – es ist ein vollständiger Theme-Stack, der den größten Teil der Shopware-Storefront-Templates komplett neu rendert. Das hat fundamentale Konsequenzen für die Plugin-Entwicklung.

### 1.1 Was ThemeWare verändert

ThemeWare überschreibt folgende Templates vollständig mit eigenen Block-Strukturen:

- `buy-widget/buy-widget.html.twig` – komplett neu strukturiert
- `cms-element-buy-box.html.twig` – eigene Render-Logik
- `product-detail/index.html.twig` – CMS-Layout statt klassischer Seite
- Nahezu alle Layout-Templates im Header/Footer-Bereich

> **Kernregel:** Wer ein Plugin für einen Shopware-Shop mit ThemeWare entwickelt, entwickelt es faktisch für ThemeWare – nicht für Shopware Core. Das Standard-Shopware-Wissen reicht nicht aus.

### 1.2 CMS-Layout statt klassischer Produktdetailseite

Ab Shopware 6.6 – und bei ThemeWare standardmäßig aktiviert – rendert die Produktdetailseite nicht mehr über `page/product-detail/index.html.twig`, sondern über das **CMS-Layout-System** (Shopping Experiences). Das bedeutet:

- Der Block `page_product_detail_buybox` **existiert nicht mehr**
- Stattdessen werden `cms-element-buy-box.html.twig` und `buy-widget/buy-widget.html.twig` verwendet
- ThemeWare fügt über `buy_widget_ordernumber_container` eigene Blöcke hinzu (Bündchen, Material, USPs, Zahlarten, Social Share)

---

## 2. Template-Hierarchie verstehen

### 2.1 Die korrekte Extends-Kette

Das ist der häufigste Fehler: Ein Plugin extended `@Storefront/...` aber ThemeWare rendert seinen eigenen Stack, der das Plugin nie aufruft.

| Situation | Korrekte Extends-Zeile |
|---|---|
| Shopware **ohne** ThemeWare | `{% sw_extends '@Storefront/storefront/component/buy-widget/buy-widget.html.twig' %}` |
| Shopware **mit** ThemeWare | `{% sw_extends '@Storefront/storefront/component/buy-widget/buy-widget.html.twig' %}` – ThemeWare ruft `{{ parent() }}` auf, daher greift der Standard-Extend trotzdem |
| Wenn ThemeWare `parent()` **NICHT** aufruft | `{% sw_extends '@TcinnThemeWareModern/storefront/...' %}` – nur als Fallback, erhöht Abhängigkeit |

### 2.2 Wie ThemeWare Blöcke strukturiert

ThemeWare verwendet in `buy-widget.html.twig` folgende Block-Reihenfolge (ermittelt durch direktes Auslesen der Datei auf dem Server):

| Zeile (ca.) | Block-Name / Inhalt |
|---|---|
| 45 | `buy_widget_ordernumber_container` – Produktnummer + ThemeWare-USPs (Vorteile, Zahlarten, Share-Links) → danach `{{ parent() }}` |
| 96 | `buy_widget_buy_container` – Mengen-Selector, Bund-Checkbox |
| 136 | `buy_widget_price` – Preisdarstellung |
| 149 | `buy_widget_delivery_informations` – Lieferstatus + ThemeWare-Labels |
| 164 | `buy_widget_buy_form` – Warenkorb-Button + Social Proofing |
| 189 | `buy_widget_ordernumber_container` – nochmals (ThemeWare-Duplikat, auskommentiert) |

> **Wichtig:** `buy_widget_ordernumber_container` ruft `{{ parent() }}` NACH dem eigenen ThemeWare-Inhalt auf. Das bedeutet: Wer NACH `{{ parent() }}` einfügt, landet zwischen Produktnummer/Material und den ThemeWare-USPs – genau die Position für eigene Produktdaten.

---

## 3. Der bewährte Ansatz: `buy_widget_ordernumber_container`

### 3.1 Warum dieser Block funktioniert

Die Erkenntnis aus der Entwicklung von WGCareInstructions und WGShopBooster: Der **einzige Block**, der zuverlässig sowohl in Shopware Core als auch in ThemeWare an der richtigen Position rendert, ist `buy_widget_ordernumber_container` – vorausgesetzt, man fügt den eigenen Inhalt **NACH** `{{ parent() }}` ein.

Die resultierende Reihenfolge auf der Produktseite:

1. Produktnummer (Shopware Core via `parent()`)
2. Bündchen / Material (Custom Fields via `parent()`)
3. **→ Eigener Plugin-Inhalt hier (nach `parent()`)**
4. Unsere Vorteile / USPs (ThemeWare, Teil des ordernumber_container-Blocks)
5. Zahlarten / Social Share (ThemeWare)

### 3.2 Das minimale Template-Gerüst

**Datei:** `src/Resources/views/storefront/component/buy-widget/buy-widget.html.twig`

```twig
{% sw_extends '@Storefront/storefront/component/buy-widget/buy-widget.html.twig' %}

{% block buy_widget_ordernumber_container %}
    {{ parent() }}

    {# Eigener Inhalt – erscheint nach Material/Bündchen,
       vor "Unsere Vorteile" von ThemeWare #}
    {% set myValue = product.customFields.my_field ?? null %}
    {% if myValue %}
        <div class="my-plugin-content">...</div>
    {% endif %}
{% endblock %}
```

### 3.3 `cms-element-buy-box.html.twig` – nur Passthrough

Das `cms-element-buy-box.html.twig` braucht bei diesem Ansatz keine eigene Logik. Es reicht ein leeres Extend:

```twig
{% sw_extends '@Storefront/storefront/element/cms-element-buy-box.html.twig' %}
{# Logik liegt in buy-widget.html.twig #}
```

> **Falle:** Wer die Logik in `cms-element-buy-box.html.twig` über `element_buy_box_inner` einfügt, riskiert falsche Positionierung – ThemeWare rendert diesen Block in einem anderen Kontext als erwartet. JavaScript-Positionierung als Workaround ist fragil und unnötig.

---

## 4. Custom Fields: Zugriff im Twig-Template

### 4.1 Produktzugriff im buy-widget-Kontext

Im buy-widget-Kontext ist die Variable `product` direkt verfügbar. Custom Fields werden über `product.customFields.feldname` angesprochen:

```twig
{% set careSet = product.customFields.wg_care_set ?? 'A' %}
{% set bundchen = product.customFields.wg_buendchen ?? null %}
```

### 4.2 Plugin-Konfiguration lesen

Plugin-Einstellungen aus `config.xml` werden über die `config()`-Funktion gelesen:

```twig
{% set enabled = config('WGCareInstructions.config.enabled') %}
{% if enabled != false and careSet and careSet != 'none' %}
```

**Wichtig:** `config()` gibt `null` zurück wenn der Wert nicht gesetzt ist. Daher immer mit `!= false` prüfen, nicht mit `== true` – sonst schlägt die Prüfung fehl wenn der Wert `null` ist.

### 4.3 `config.xml` – häufiger Fehler mit `<n>`-Tag

Shopware erwartet in `config.xml` den Tag `<name>` für Konfigurationsfelder, aber das Shopware-Schema validiert auf `<n>` für `input-field`. Falsche Tags führen zu einem Parser-Fehler beim Plugin-Installieren:

```xml
<!-- RICHTIG -->
<input-field type="bool">
    <name>enabled</name>
    <label>Pflegehinweise aktivieren</label>
</input-field>

<!-- FALSCH – führt zu: Element n: This element is not expected -->
<input-field type="bool">
    <n>enabled</n>
</input-field>
```

---

## 5. Assets: Bilder und Dateien im Plugin

### 5.1 Verzeichnisstruktur

Bilder und statische Dateien gehören nach `src/Resources/public/`. Shopware kopiert diesen Ordner beim `assets:install`-Befehl in den Public-Bereich und macht ihn unter `bundles/pluginname/` erreichbar:

| Pfad im Plugin | URL im Storefront |
|---|---|
| `src/Resources/public/img/symbol.png` | `bundles/wgcareinstructions/img/symbol.png` |
| `src/Resources/public/js/script.js` | `bundles/wgcareinstructions/js/script.js` |
| `src/Resources/public/fonts/font.woff2` | `bundles/wgcareinstructions/fonts/font.woff2` |

### 5.2 Asset-Funktion im Twig-Template

```twig
<img src="{{ asset('bundles/wgcareinstructions/img/symbol.png') }}" alt="Symbol">
```

Nach jeder Plugin-Änderung mit neuen Dateien in `/public/` muss auf dem Server ausgeführt werden:

```bash
php bin/console assets:install
php bin/console cache:clear
```

### 5.3 Dateinamen-Konventionen

**Wichtig:** Sonderzeichen und Umlaute in Dateinamen vermeiden. Beim Hochladen und Kopieren können Kodierungsprobleme entstehen. Umlaute umbenennen:

- `schonwäsche-40.png` → `schonwaesche-40.png`
- `nicht_bügeln.png` → `nicht-buegeln.png`
- Bindestriche statt Unterstriche (konsistenter)

---

## 6. Deployment-Checkliste

### 6.1 Erstinstallation

Reihenfolge strikt einhalten – besonders Schritt 4 (Theme-Kompilierung) wird oft vergessen:

| Schritt | Befehl / Aktion |
|---|---|
| 1. ZIP hochladen | Admin → Erweiterungen → Meine Erweiterungen → Upload |
| 2. Installieren | Admin → Installieren-Button klicken |
| 3. Aktivieren | Admin → Aktivieren-Button klicken |
| 4. Theme kompilieren | `php bin/console theme:compile` (oder Admin → Themes → Kompilieren) |
| 5. Assets installieren | `php bin/console assets:install` |
| 6. Cache leeren | `php bin/console cache:clear` |
| 7. HTTP-Cache leeren | Admin → Einstellungen → Cache → Alle Caches leeren |

### 6.2 Update / Änderungen

Nach Code-Änderungen reicht meist:

- Neues ZIP hochladen & Plugin aktualisieren (im Admin)
- `theme:compile` – bei SCSS-Änderungen **zwingend**
- `assets:install` – bei neuen Dateien in `/public/`
- `cache:clear` – **immer**

> **HTTP-Cache-Problem:** Shopware hat einen zweistufigen Cache. Auch nach `cache:clear` kann der HTTP-Cache noch alte Seiten ausliefern. Immer zusätzlich den HTTP-Cache im Admin leeren oder mit einem `Cache-Control: no-cache` Header testen.

### 6.3 Server-Besonderheiten (Timme Hosting / k72a80)

- Kein Python3, kein npm auf dem Server → keine Node.js-Build-Schritte
- PHP-CLI vorhanden: `php /web/public/bin/console [...]`
- HTTP-Cache muss separat geleert werden (Admin oder Varnish)
- File-Schreibrechte: ggf. über PHP-Skript schreiben wenn SSH-Rechte fehlen

---

## 7. Debugging-Strategien

### 7.1 Prüfen ob Plugin-Template greift

Direkt auf dem Server via SSH prüfen ob der eigene HTML-Code im Seiten-Output erscheint:

```bash
curl -s http://neu.wenk-socken.ch/[produkt-url] | grep -c "mein-css-class"
```

Gibt `0` zurück → Template greift nicht. Gibt `> 0` zurück → Template greift, aber ggf. HTTP-Cache.

### 7.2 Block-Namen in ThemeWare ermitteln

Immer direkt die ThemeWare-Templates auf dem Server auslesen, nie raten:

```bash
grep -n "block" /web/public/custom/plugins/TcinnThemeWareModern/src/Resources/views/storefront/component/buy-widget/buy-widget.html.twig
```

Oder einen bestimmten Bereich anzeigen:

```bash
sed -n '45,95p' /web/public/custom/plugins/TcinnThemeWareModern/src/Resources/views/storefront/component/buy-widget/buy-widget.html.twig
```

### 7.3 Twig-Cache-Dateien prüfen

Wenn das Template korrekt aussieht aber nicht greift, den kompilierten Twig-Cache prüfen:

```bash
grep -r "mein-css-class" /web/public/var/cache/prod_*/twig/ | grep "yield\|include" | head -5
```

Wenn hier nichts gefunden wird, ist das Template nicht im Kompilierungspfad.

### 7.4 Häufige Fehlerursachen auf einen Blick

| Symptom | Ursache & Lösung |
|---|---|
| Plugin greift nicht, 0 Treffer im HTML | Theme nicht neu kompiliert → `theme:compile` ausführen |
| Plugin greift, aber falsche Position | Falscher Block verwendet → `buy_widget_ordernumber_container` + nach `parent()` |
| Assets (Bilder) 404 | `assets:install` nicht ausgeführt, oder Dateiname mit Sonderzeichen |
| Install-Fehler: "Element n not expected" | `config.xml` verwendet `<n>` statt `<name>` in `input-field` |
| Seite zeigt alten Stand nach `cache:clear` | HTTP-Cache noch aktiv → Admin-Cache leeren oder no-cache Header testen |
| Template greift in Core aber nicht in ThemeWare | ThemeWare ruft `parent()` nicht auf → ThemeWare-Template direkt auslesen |
| Custom Fields leer im Template | Produkt hat Custom Field nicht gesetzt oder Feldname falsch |
| Varianten laden Seite neu, Plugin verschwindet | AJAX-Reload des buy-widgets überschreibt DOM → `buy-widget.html.twig` nutzen statt JS-Injektion |

---

## 8. Do's und Don'ts

### 8.1 Do's – Was funktioniert

- `buy_widget_ordernumber_container` mit `{{ parent() }}` zuerst, dann eigener Inhalt
- Immer `@Storefront/...` extenden – ThemeWare ruft `parent()` auf
- Dateinamen ohne Sonderzeichen/Umlaute in `/public/`
- `config()` immer mit `!= false` prüfen, nicht mit `== true`
- Reihenfolge beim Deployment: install → activate → theme:compile → assets:install → cache:clear
- ThemeWare-Templates direkt auf dem Server auslesen um Block-Namen zu verifizieren
- `product.customFields.feldname` mit `??` Fallback absichern

### 8.2 Don'ts – Was nicht funktioniert

- Logik in `element_buy_box_inner` einfügen und auf korrekte Position hoffen
- JavaScript-DOM-Manipulation zur Positionierung (fragil, versagt bei AJAX-Reload)
- `page_product_detail_buybox` als Block nutzen (existiert bei CMS-Layouts nicht)
- Bilder mit Umlauten im Dateinamen (`schonwäsche.png`)
- `<n>` statt `<name>` in `config.xml` input-fields
- Blindes Raten von Block-Namen ohne Blick in die ThemeWare-Templates
- Nach Code-Änderungen nur `cache:clear` ohne `theme:compile`
- `@TcinnThemeWareModern` als Extend-Basis (erhöht Theme-Abhängigkeit, bricht bei Theme-Updates)

---

## 9. Empfohlene Plugin-Dateistruktur

```
MeinPlugin/
├── composer.json
└── src/
    ├── MeinPlugin.php              ← Optional: Custom Fields via Install-Hook
    └── Resources/
        ├── config/
        │   └── config.xml          ← Plugin-Einstellungen
        ├── public/
        │   └── img/                ← Bilder (assets:install → bundles/meinplugin/img/)
        ├── app/
        │   └── storefront/
        │       └── src/
        │           └── scss/
        │               └── base.scss    ← Storefront-CSS
        └── views/
            └── storefront/
                ├── component/
                │   └── buy-widget/
                │       └── buy-widget.html.twig    ← HAUPTEINSTIEGSPUNKT
                └── element/
                    └── cms-element-buy-box.html.twig  ← Nur Passthrough
```

---

## 10. Kopiervorlage: Minimales Plugin-Gerüst

### `composer.json`

```json
{
    "name": "webagentur-gaul/mein-plugin",
    "type": "shopware-platform-plugin",
    "version": "1.0.0",
    "extra": {
        "shopware-plugin-class": "WebagenturGaul\\MeinPlugin\\MeinPlugin",
        "label": { "de-DE": "Mein Plugin", "en-GB": "My Plugin" }
    },
    "autoload": { "psr-4": { "WebagenturGaul\\MeinPlugin\\": "src/" } }
}
```

### `config.xml` (Auszug)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <title>Mein Plugin</title>
        <input-field type="bool">
            <name>enabled</name>          <!-- NICHT <n> -->
            <label>Aktivieren</label>
            <defaultValue>true</defaultValue>
        </input-field>
    </card>
</config>
```

### `buy-widget.html.twig` (Hauptdatei)

```twig
{% sw_extends '@Storefront/storefront/component/buy-widget/buy-widget.html.twig' %}

{% block buy_widget_ordernumber_container %}
    {{ parent() }}

    {% set enabled = config('MeinPlugin.config.enabled') %}
    {% set wert = product.customFields.mein_feld ?? null %}

    {% if enabled != false and wert %}
    <div class="mein-plugin-block">
        {{ wert }}
    </div>
    {% endif %}
{% endblock %}
```

---

## 11. Plugin sauber löschen – der sichere Weg

Das Löschen eines Plugins ist fehleranfälliger als erwartet – besonders wenn das Plugin Custom Fields registriert hat oder ein DAL-Fehler beim Uninstall auftritt.

### 11.1 Normalfall – funktioniert wenn Plugin fehlerfrei

```bash
php /web/public/bin/console plugin:deactivate MeinPlugin
php /web/public/bin/console plugin:uninstall MeinPlugin
rm -rf /web/public/custom/plugins/MeinPlugin
php /web/public/bin/console plugin:refresh
php /web/public/bin/console cache:clear
```

### 11.2 Fehlerfall: "Input should contain a list of associative arrays"

Dieser Fehler tritt auf wenn die `removeCustomFields()`-Methode im Plugin einen DAL-Fehler wirft – z.B. weil die Custom Field Set-Struktur nicht mehr der DB entspricht. `plugin:uninstall` schlägt dann komplett fehl.

Diesen Fehler haben wir bei WGCareInstructions erlebt. Lösung: Plugin-Dateien zuerst löschen, dann Datenbankzeile direkt entfernen.

**Schritt 1: Plugin-Dateien löschen**

```bash
rm -rf /web/public/custom/plugins/MeinPlugin
php /web/public/bin/console plugin:refresh
```

**Schritt 2: DB-Credentials ermitteln**

```bash
cat /web/public/.env.local
```

Format: `DATABASE_URL=mysql://USER:PASSWORD@HOST:PORT/DBNAME`

> **Wichtig:** Die `.env` (ohne `.local`) enthält oft Platzhalter – immer `.env.local` prüfen!

**Schritt 3: Direkt per MySQL löschen**

```bash
mysql -h 127.0.0.1 -P PORT -u USER -p'PASSWORD' DBNAME \
  -e "DELETE FROM plugin WHERE name='MeinPlugin';"
```

**Schritt 4: Refresh & Cache**

```bash
php /web/public/bin/console plugin:refresh
php /web/public/bin/console cache:clear
```

### 11.3 Häufige Fallstricke beim Löschen

| Fehler / Symptom | Ursache & Lösung |
|---|---|
| "Plugin must be activated. Skipping." | Plugin ist bereits inaktiv → `deactivate` überspringen, direkt `uninstall` |
| "Input should contain a list of associative arrays" | DAL-Fehler in `removeCustomFields()` → Dateien löschen + DB direkt bereinigen (Schritt 11.2) |
| `ERROR 2002: Can't connect via socket` | MySQL läuft nicht auf Standard-Socket → `-h 127.0.0.1` verwenden statt `localhost` |
| `ERROR 1045: Access denied` | Falsche Credentials in `.env` → `.env.local` prüfen, dort stehen die echten Daten |
| `cache:clear` wirft `InvalidArgumentException` | SQL-Statement wurde versehentlich als `APP_ENV` übergeben → Befehle einzeln ausführen, kein `&&`-Chaining mit SQL |
| "Command database:execute-sql not defined" | Shopware-Version hat diesen Befehl nicht → direkt `mysql`-Client nutzen |
| Plugin nach `plugin:refresh` noch sichtbar | DB-Eintrag noch vorhanden → `DELETE FROM plugin WHERE name='...'` ausführen |

### 11.4 MySQL-Verbindung auf Timme Hosting

Auf dem Timme-Server (k72a80.meinserver.io) läuft MySQL **nicht** auf dem Standard-Port 3306 und **nicht** über den Standard-Socket. Die Verbindungsparameter aus `.env.local`:

| Parameter | Wert (Beispiel Wenk) |
|---|---|
| Host | `127.0.0.1` (nicht `localhost`!) |
| Port | `14501` (nicht `3306`!) |
| User | `c1wenk_sw6_user` |
| Datenbank | `c1C1wenk_sw6_user` |
| Password | aus `.env.local` |

```bash
mysql -h 127.0.0.1 -P 14501 -u c1wenk_sw6_user -p'PASSWORT' c1C1wenk_sw6_user \
  -e "DELETE FROM plugin WHERE name='WGCareInstructions';"
```

### 11.5 Custom Fields beim Löschen

Wenn ein Plugin Custom Fields in der `install()`-Methode anlegt, sollte die `uninstall()`-Methode diese wieder entfernen. Folgendes Muster ist robust:

```php
public function uninstall(UninstallContext $ctx): void {
    parent::uninstall($ctx);
    if ($ctx->keepUserData()) return;

    $repo = $this->container->get('custom_field_set.repository');
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('name', 'mein_custom_field_set'));
    $result = $repo->search($criteria, $ctx->getContext());

    if ($result->getTotal() === 0) return;  // ← wichtig: früh abbrechen

    $ids = array_map(fn($e) => ['id' => $e->getId()], $result->getElements());
    $repo->delete($ids, $ctx->getContext());
}
```

> **Der häufigste DAL-Fehler:** `$repo->delete()` erwartet ein Array von Arrays (`[["id" => "..."]]`), nicht ein Array von IDs (`["..."]`). Wenn `getElements()` leer ist und trotzdem `delete()` aufgerufen wird, crasht Shopware mit "Input should contain a list of associative arrays."

---

## 12. Admin-Extension Development (Vue.js)

### 12.1 Admin-Build-Prozess

Shopware 6 Admin-Extensions verwenden Vue.js und müssen über den Admin-Build-Prozess kompiliert werden. Dieser Prozess ist **serverlastig** und erfordert Node.js:

```bash
cd /web/public && php bin/console bundle:dump && bin/build-administration.sh
```

> **Wichtig:** Dieser Build kann 2-5 Minuten dauern und benötigt Node.js (v20+) und npm auf dem Server.

### 12.2 Admin-Modul registrieren

Ein Admin-Modul wird über `main.js` im Pfad `src/Resources/app/administration/src/main.js` registriert:

```javascript
import './module/mein-modul';
```

Das Modul selbst liegt in `src/Resources/app/administration/src/module/mein-modul/index.js`.

### 12.3 Nach Admin-Änderungen

Nach jeder Änderung an Admin-Vue-Komponenten **muss** der Admin neu gebaut werden:

```bash
cd /web/public && php bin/console bundle:dump && bin/build-administration.sh
php bin/console cache:clear
```

Dann im Browser den Admin mit `Ctrl+Shift+R` hart neu laden.

### 12.4 Vorkompilierte Bundles deployen (ohne Node.js auf dem Server)

Auf Shared-Hosting (z.B. Timme) ist Node.js nicht verfügbar. Der Admin-Build muss lokal gemacht und das fertige Bundle ins Plugin eingecheckt werden.

**Pfad für vorkompilierte Bundles:**
```
src/Resources/public/administration/
├── .vite/
│   ├── entrypoints.json    ← KRITISCH: muss richtiges Format haben!
│   └── manifest.json
└── assets/
    └── plugin-name-HASH.js
```

`assets:install` kopiert diese Dateien automatisch nach `/public/bundles/pluginname/`.

### 12.5 Das `entrypoints.json` Format-Problem (kritisch!)

Das **PentatrionViteBundle** in Shopware 6.6+ erwartet `entrypoints.json` in einem **spezifischen Format**. Falsches Format = Bundle wird nicht geladen, Modul erscheint nicht im Admin-Menü.

**❌ FALSCH** (Plugin lädt nicht, kein Fehler im Browser):
```json
{
  "w-g-qr-bill": {
    "js": [
      "assets/w-g-qr-bill-HASH.js"
    ]
  }
}
```

**✅ RICHTIG** (so wird das Bundle vom PentatrionViteBundle geladen):
```json
{
  "base": "/bundles/pluginname/administration/",
  "entryPoints": {
    "plugin-name": {
      "css": [],
      "dynamic": [],
      "js": [
        "/bundles/pluginname/administration/assets/plugin-name-HASH.js"
      ],
      "legacy": false,
      "preload": []
    }
  },
  "legacy": false,
  "metadatas": {},
  "version": [
    "7.1.0",
    7,
    1,
    0
  ],
  "viteServer": null
}
```

**Wichtige Punkte:**
- `base` muss mit `/bundles/<pluginname-lowercase>/administration/` matchen
- `entryPoints.X.js` Pfade müssen **absolute** Pfade sein (mit führendem `/bundles/`), nicht relative
- Der Plugin-Key in `entryPoints` ist der `technicalName` aus `plugins.json` (z.B. `w-g-qr-bill`)

### 12.6 Admin-Modul Loading-Pipeline (Debug-Reihenfolge)

Wenn das Modul nicht im Admin-Menü erscheint, prüfe in dieser Reihenfolge:

1. **Plugin aktiv?**
   ```bash
   php bin/console plugin:list | grep MeinPlugin
   ```
   Sollte `Yes` für Active und Installed zeigen.

2. **In `var/plugins.json` registriert?**
   ```bash
   grep -A10 MeinPlugin /web/public/var/plugins.json
   ```
   Sollte `entryFilePath: "Resources/app/administration/src/main.js"` enthalten.

3. **Bundle-Files vorhanden?**
   ```bash
   ls /web/public/public/bundles/meinplugin/administration/assets/
   ```
   Sollte mind. eine `.js`-Datei enthalten.

4. **Bundle per HTTP erreichbar?**
   ```bash
   curl -I http://shop.tld/bundles/meinplugin/administration/assets/X.js
   ```
   Sollte `HTTP 200` zurückgeben.

5. **`entrypoints.json` im richtigen Format?** (siehe 12.5)
   ```bash
   cat /web/public/public/bundles/meinplugin/administration/.vite/entrypoints.json
   ```
   Vergleiche mit einem funktionierenden Plugin (z.B. WGFaq).

6. **JS lädt im Browser?** (in der Admin-Browser-Console eingeben):
   ```javascript
   performance.getEntriesByType('resource').filter(r => r.name.includes('mein-plugin')).map(r => r.name)
   ```
   Leeres Array `[]` = JS wird nicht geladen → Punkt 5 prüfen.

7. **Modul registriert?**
   ```javascript
   Shopware.Module.getModuleRegistry().has('mein-modul')
   ```
   `false` = JS hat einen Fehler vor `Module.register()`.

### 12.7 Häufige Fehler bei Admin-Modulen

| Symptom | Ursache & Lösung |
|---|---|
| Modul erscheint nicht im Menü | `entrypoints.json` hat falsches Format → siehe 12.5 |
| `this.loginService.getHeader is not a function` | In Shopware 6.5+ deprecated → `Shopware.Application.getContainer('init').httpClient` mit `Authorization: 'Bearer ' + Shopware.Context.api.authToken.access` nutzen |
| API-Endpunkt liefert 404 | Route-Name kollidiert mit Auto-Entity-Routes → `/api/_action/...` Prefix verwenden |
| Bundle wird nach `assets:install` überschrieben | Source-Datei in `src/Resources/public/...` muss aktualisiert werden, nicht nur die Bundle-Datei |
| Browser-Cache zeigt alte Version | Hash-Dateinamen verwenden (`xxx-CgQqNnn4.js`), nicht generische (`xxx.js`) — und Inkognito-Fenster zum Testen |
| `loginService` in einem Plugin geht, im anderen nicht | Versionsabhängig — neue Plugins immer mit `httpClient` schreiben |

### 12.8 API-Routen: Konflikt mit Auto-Entity-Routen vermeiden

Wenn das Plugin eine eigene Entity hat (z.B. `wg_dunning`), generiert Shopware automatisch CRUD-Routen:
- `GET /api/wg-dunning{path}` → `api.wg_dunning.list`
- `POST /api/wg-dunning{path}` → `api.wg_dunning.create`

Diese **kollidieren mit gleichnamigen Custom-Controller-Routen**! Custom-Controller-Routen müssen darum den Shopware-Konvention-Prefix `/_action/` verwenden:

```php
#[Route(
    path: '/api/_action/wg-dunning/list',         // ← _action im Pfad
    name: 'api.action.wg_dunning.list',           // ← .action im Namen
    methods: ['GET']
)]
public function listDunnings(): JsonResponse { ... }
```

Im Admin-JS:
```javascript
this.httpClient.get('/_action/wg-dunning/list', { headers: this.authHeaders })
```

### 12.9 httpClient statt loginService.getHeader

`loginService.getHeader()` ist in Shopware 6.5+ deprecated. Modernes Pattern:

```javascript
const { Component, Mixin } = Shopware;

Component.register('mein-list', {
    template,
    mixins: [Mixin.getByName('notification')],

    computed: {
        httpClient() {
            return Shopware.Application.getContainer('init').httpClient;
        },
        authHeaders() {
            return {
                Accept: 'application/json',
                Authorization: 'Bearer ' + Shopware.Context.api.authToken.access,
                'Content-Type': 'application/json',
            };
        },
    },

    methods: {
        async loadData() {
            const response = await this.httpClient.get('/_action/mein-endpoint', {
                headers: this.authHeaders,
            });
            this.items = response.data.data;
        },

        async downloadPdf(id) {
            const response = await this.httpClient.get(`/_action/mein-endpoint/${id}/pdf`, {
                headers: { Authorization: 'Bearer ' + Shopware.Context.api.authToken.access },
                responseType: 'blob',
            });
            // ... blob-Download-Logik
        },
    },
});
```

---

## 13. Dokument-Templates (Rechnungen, Mahnungen, PDFs)

### 13.1 Block-Hierarchie in Document-Templates

Shopware-Document-Templates haben eine klare Hierarchie. Wer den falschen Block überschreibt, fügt Inhalt **außerhalb** der HTML-Struktur ein → leere Seiten oder broken layout.

| Block | Position | Zweck |
|---|---|---|
| `document_base` | **Outer wrapper** (`<html>`, `<head>`, `<body>`, `<footer>`) | Komplette PDF-Struktur — **nicht überschreiben für Custom Content!** |
| `document_body` | Innerhalb `<body>`, vor Footer | **Hier eigenen Content einfügen** |
| `document_header` | Logo + Firmendaten oben | Standardmäßig aus `document_base_config` |
| `document_footer` | Fußzeile mit Bankdaten etc. | Standardmäßig aus `document_base_config` |

**❌ FALSCH** — fügt Content nach `</html>` ein, erzeugt 4-6 leere Seiten:
```twig
{% block document_base %}
    {{ parent() }}
    <div>Mein Custom Content</div>   {# außerhalb des Dokuments! #}
{% endblock %}
```

**✅ RICHTIG** — fügt Content innerhalb des Dokuments ein:
```twig
{% block document_body %}
    {{ parent() }}
    <div>Mein Custom Content</div>
{% endblock %}
```

### 13.2 Plugin-SystemConfig in Document-Templates lesen

**Falle:** In Document-Templates gibt es zwei verschiedene `config()`-Funktionen mit **unterschiedlicher Semantik**:

| Aufruf | Quelle | Ergebnis |
|---|---|---|
| `{{ config('MeinPlugin.config.key') }}` | `document_base_config` Tabelle (NICHT Plugin-Config!) | Liefert oft `null` oder Default `"Example Company"` |
| `{{ system_config('MeinPlugin.config.key') }}` | Plugin-SystemConfig (richtig) | Liefert tatsächlichen Plugin-Wert |

**Aber:** `system_config()` ist nicht überall verfügbar. Robuster Ansatz: **Eigene Twig-Funktion** in der Plugin-Twig-Extension:

```php
// QrBillTwigExtension.php
class QrBillTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wg_qr_config', [$this, 'getPluginConfig']),
        ];
    }

    public function getPluginConfig(string $key, ?string $salesChannelId = null): string
    {
        return (string) ($this->systemConfigService->get(
            'MeinPlugin.config.' . $key,
            $salesChannelId
        ) ?? '');
    }
}
```

Im Template:
```twig
{{ wg_qr_config('creditorName', order.salesChannelId) }}
```

### 13.3 Rechnungs-Header zeigt "Example Company" trotz korrekter Plugin-Config

Der **Rechnungskopf/Footer** zeigt nicht die Plugin-Config, sondern die **Document-Konfiguration**:

→ Admin → **Einstellungen → Shop → Dokumente → Rechnung** → "Geschäftseinstellungen" Card

Dort müssen alle Felder ausgefüllt werden (Firmenname, Adresse, Bank, USt-Nr., Geschäftsführer, etc.). Diese Daten werden von Shopware-Core ins Header- und Footer-HTML eingebunden — unabhängig von Plugin-Code.

### 13.4 Generierte PDFs sind gecacht

Sobald ein Dokument (Rechnung, Mahnung) generiert wurde, wird die PDF-Datei **dauerhaft gespeichert**. Jeder weitere Download liefert die alte Version — Template-Änderungen werden nicht sichtbar.

**Lösung:** Eine **neue** Rechnung erstellen (für dieselbe Bestellung möglich, oder neue Test-Bestellung).

Im Admin: Bestellung öffnen → Tab "Dokumente" → "Rechnung erstellen" Button → die **letzte** Rechnung in der Liste herunterladen.

### 13.5 DomPDF Limitierungen (Shopware-Standard PDF-Renderer)

| Problem | Ursache | Lösung |
|---|---|---|
| Logo erscheint als leeres Kästchen | DomPDF unterstützt nur PNG/JPG | Logo als PNG hochladen (kein SVG/WebP) |
| Logo-Datei nicht gefunden | DomPDF lädt Bilder via `APP_URL` per HTTP/S | `APP_URL` muss auf öffentliche Domain zeigen, nicht `127.0.0.1` |
| Logo lädt nicht obwohl URL korrekt | Server kann sich selbst nicht per HTTPS erreichen (Loopback-Problem auf Shared Hosting) | Logo lokal verfügbar machen, ggf. DomPDF `chroot` setzen |
| Leerzeichen im Bilddateinamen verursachen 404 | URL-Encoding-Probleme | Dateinamen ohne Leerzeichen verwenden |
| `page-break-before: always` erzeugt leere Folgeseite | DomPDF interpretiert page-break vor leerem Content | Page-break weglassen oder bedingt einfügen |

### 13.6 APP_URL auf Shared Hosting

Auf vielen Shared-Hostings kann der Server **keine HTTPS-Requests zu sich selbst** machen (z.B. wegen fehlender DNS-Auflösung im PHP-Container).

Test:
```bash
php -r "echo @file_get_contents('https://www.shop.tld/favicon.ico', false, null, 0, 4) ? 'OK' : 'FAIL'; echo PHP_EOL;"
```

Wenn `FAIL` → DomPDF kann keine Logos via HTTP laden. Workarounds:
1. APP_URL trotzdem korrekt setzen (für externe Verlinkung)
2. Wichtige Bilder (Logo) möglichst klein und im PNG-Format
3. Falls Logo nicht zwingend → Document-Konfiguration ohne Logo nutzen

**Wichtig:** APP_URL steht in **`.env.local`**, nicht in `.env`! Die `.env` enthält oft Platzhalter (`http://127.0.0.1:8000`).

### 13.7 Custom-Twig-Funktion für QR-Code-Generierung

QR-Codes (z.B. Schweizer QR-Rechnung) als Data-URI im HTML einbetten — nicht als externe URL. Dann braucht DomPDF keine HTTP-Requests:

```php
public function generateQrBillSvg(...): string
{
    $qrBill = $this->createQrBill(...);
    return $qrBill->getQrCode()->getDataUri();  // data:image/svg+xml;base64,...
}
```

Im Template:
```twig
<img src="{{ wg_qr_bill_svg(amount, currency, ...) }}" alt="QR Code" />
```

> **Hinweis:** SVG via Data-URI funktioniert auch in DomPDF, weil das SVG inline geparst wird (kein HTTP-Request).

### 13.8 Document-Template Debug-Strategien

1. **PDF zu groß / leere Seiten?** → Falscher Block (siehe 13.1) oder unbedingter `page-break`
2. **"Example Company" im PDF?** → Document-Konfiguration nicht ausgefüllt (siehe 13.3)
3. **Plugin-Config-Wert nicht im PDF?** → `config()` liefert document_base_config — eigene Twig-Funktion bauen (siehe 13.2)
4. **Bilder fehlen?** → DomPDF-Format-Limitierung oder APP_URL falsch (siehe 13.5/13.6)
5. **Änderungen nicht sichtbar?** → PDF gecacht — neue Rechnung erstellen (siehe 13.4)

---

*Stand: April 2026 | Shopware 6.6.x | ThemeWare Modern Pro 4.2.x*

**— Webagentur Gaul | webagentur-gaul.de —**
