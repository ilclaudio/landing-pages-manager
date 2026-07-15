# Gestione Multilingua

Questo documento descrive **come il plugin gestisce oggi il multilingua** (Step 3 - Language Adapter), seguendo l'implementazione reale attuale in `includes/language-adapters/`.

## Panoramica

KKLPM non implementa una propria logica di traduzione. Si limita a fare da wrapper, dietro un confine interno stabile, verso i plugin di traduzione di terze parti gia` installati sul sito (WPML, Polylang, TranslatePress, Weglot, MultilingualPress).

Il principio e` **adapter-first**: quando un plugin multilingua supportato e` attivo, il Domain Router chiede a quel plugin, tramite un adapter dedicato, quale sia la lingua corrente e quale sia la pagina tradotta corrispondente a un mapping. Quando nessun plugin multilingua e` attivo, un adapter Null lascia il comportamento identico a un sito monolingua.

Il resto del plugin (matcher, repository, admin page) non conosce nessuna API di terze parti: parla solo con `KKLPM_Language_Adapter_Interface` tramite `KKLPM_Language_Adapter_Resolver`.

**Un adapter per plugin, uno solo attivo per volta.** In `includes/language-adapters/` esiste una classe dedicata per ciascuno dei cinque plugin supportati (`KKLPM_Language_Adapter_WPML`, `..._Polylang`, `..._TranslatePress`, `..._Weglot`, `..._MultilingualPress`), piu` l'adapter Null di fallback: tutte e cinque le classi esistono sempre nel codice, indipendentemente da quali plugin siano installati. Ma su un sito reale e` normale che sia attivo un solo plugin di traduzione alla volta, quindi ad ogni richiesta il resolver seleziona **un solo adapter** — quello il cui plugin risulta effettivamente installato e attivo — e ignora gli altri quattro. Non c'e` nessuna logica che combina o interroga piu` plugin di traduzione insieme nella stessa richiesta.

## Come si configura

Non c'e` nessuna configurazione dedicata da compilare nel plugin.

1. Installa e attiva **uno solo** dei plugin supportati (WPML, Polylang, TranslatePress, Weglot, MultilingualPress).
2. Configuralo normalmente per conto suo (lingue attive, traduzioni delle pagine), seguendo la sua interfaccia abituale.
3. Configura i mapping del Domain Router come sempre (`Settings -> Landing Domain Router`), puntando alla pagina sorgente. Il form non chiede e non mostra nessun campo lingua: la colonna `lang` esiste ancora nella tabella `wp_landing_domain_map` per compatibilita` tecnica, ma non viene letta da nessuna parte a runtime.
4. Se non e` attivo nessun plugin multilingua, non serve fare nulla: il routing si comporta esattamente come prima dello Step 3.

Se sono installati piu` plugin multilingua contemporaneamente (caso non raccomandato), vince quello con priorita` piu` alta nell'ordine di default:

```
WPML > Polylang > TranslatePress > Weglot > MultilingualPress > Null
```

L'ordine e` personalizzabile con un filtro standard WordPress, ad esempio per forzare Polylang anche se WPML risultasse tecnicamente rilevabile:

```php
add_filter( 'kklpm_language_adapter_priority', function ( $default_order ) {
    return array( 'KKLPM_Language_Adapter_Polylang', 'KKLPM_Language_Adapter_Null' );
} );
```

## Come il plugin gestisce le chiamate

Il punto di ingresso e` sempre lo stesso hook gia` usato dal Domain Router: `parse_request`, gestito da `KKLPM_Domain_Router_Module::handle_parse_request()`.

Flusso, passo per passo:

1. Il matcher del Domain Router risolve host/path verso una **pagina sorgente**, esattamente come nello Step 2 (nessuna modifica a questa parte).
2. Se la pagina sorgente e` valida, `resolve_translated_page_id()` chiede l'adapter attivo a `KKLPM_Language_Adapter_Resolver::get_active_adapter()`.
3. Il resolver instanzia i candidati in ordine di priorita` **solo per interrogare `is_active()`** (un semplice controllo `defined()`/`function_exists()`, nessuna chiamata reale al plugin di traduzione finche` il guard non conferma che e` davvero attivo) e restituisce il primo attivo, oppure l'adapter Null come fallback garantito. Il risultato resta in cache per tutta la request.
4. Prima di chiedere la lingua all'adapter, il router controlla se il visitatore ne ha scelta esplicitamente una tramite il parametro `?kklpm_lang=` (vedi sezione dedicata piu` sotto). Se presente, questo valore ha sempre la precedenza.
5. Altrimenti, l'adapter attivo espone `get_current_language()`. Se restituisce `null` (nessun plugin attivo, o lingua non risolvibile, e nessun override esplicito), il router non cambia nulla: la pagina servita resta quella sorgente.
6. Se esiste una lingua (esplicita o rilevata dall'adapter), il router chiede `get_translated_page_id( $page_id_sorgente, $lingua )`.
7. Se l'adapter trova una traduzione, il router serve **quella** pagina al posto della sorgente, mantenendo host/path della richiesta originale invariati nel browser (nessun redirect, stesso meccanismo gia` usato per i mapping dello Step 2).
8. Se l'adapter non trova nessuna traduzione per quella lingua (esplicita o rilevata), il router **non fallisce**: resta sulla pagina sorgente, per non rompere mai il routing esistente.

Il codice corrispondente e` in `includes/domain-router/class-kklpm-domain-router-module.php`, metodo `resolve_translated_page_id()`.

## Come vengono gestiti i vari plugin di traduzione

Ogni plugin ha un adapter dedicato in `includes/language-adapters/`, con rilevamento basato solo su `defined()`/`function_exists()` (nessuna dipendenza rigida, nessun `use`/`require` di classi di terze parti):

| Plugin | Classe adapter | Rilevamento | Lingua corrente | Pagina tradotta |
|---|---|---|---|---|
| WPML | `KKLPM_Language_Adapter_WPML` | `defined('ICL_SITEPRESS_VERSION')` | filtro `wpml_current_language` | filtro `wpml_object_id` |
| Polylang | `KKLPM_Language_Adapter_Polylang` | `function_exists('pll_current_language')` | `pll_current_language()` | `pll_get_post()` |
| TranslatePress | `KKLPM_Language_Adapter_TranslatePress` | `defined('TRP_PLUGIN_VERSION')` | globale `$TRP_LANGUAGE` | stessa pagina fisica (TRP non ha una pagina separata per lingua) |
| Weglot | `KKLPM_Language_Adapter_Weglot` | `defined('WEGLOT_VERSION')` | `weglot_get_current_language()` | stessa pagina fisica (Weglot non ha una pagina separata per lingua) |
| MultilingualPress | `KKLPM_Language_Adapter_MultilingualPress` | `defined('MULTILINGUALPRESS_VERSION')` | lingua del sito corrente nella rete multisite | `mlp_get_linked_elements()`, filtrato per la lingua del sito collegato |
| Nessuno | `KKLPM_Language_Adapter_Null` | sempre attivo, ultima opzione | nessuna (`null`) | identity: restituisce sempre la stessa pagina sorgente |

Per WPML e Polylang la traduzione e` un **post separato** (pagina tradotta con un proprio ID). Per TranslatePress e Weglot, invece, il contenuto viene tradotto dinamicamente sulla stessa pagina fisica tramite URL con prefisso lingua: l'adapter non ha nulla da tradurre a livello di ID pagina, quindi restituisce sempre l'ID sorgente. Per MultilingualPress la traduzione e` un post su un altro sito della rete multisite, collegato tramite le relazioni MLP.

## Esempio pratico

Sito con **Polylang** attivo, lingue configurate `it` (default) ed `fr`.

- Pagina sorgente "Promo" (ID `42`, lingua `it`).
- Traduzione collegata in Polylang: "Promo FR" (ID `57`, lingua `fr`).
- Mapping nel Domain Router: `Type = subpath`, `Value = /promo`, `Page = Promo (42)`, `Active = si`.

**Visitatore in italiano** (nessun prefisso lingua o lingua di default): `parse_request` risolve `/promo` verso la pagina `42`. L'adapter Polylang restituisce `get_current_language() = 'it'`. `get_translated_page_id(42, 'it')` restituisce `42` stesso (la sorgente e` gia` in italiano): la pagina servita e` `42`.

**Visitatore in francese** (es. `/fr/promo` se Polylang usa prefissi di lingua nell'URL): `parse_request` risolve comunque il path verso la pagina sorgente `42`. L'adapter restituisce `get_current_language() = 'fr'`. `get_translated_page_id(42, 'fr')` trova la traduzione e restituisce `57`: il router serve la pagina `57` al posto della `42`, mantenendo l'URL richiesto invariato nel browser (nessun redirect).

**Traduzione mancante**: se per la pagina `42` non esistesse ancora nessuna traduzione francese in Polylang, `get_translated_page_id(42, 'fr')` restituirebbe `null`: il router resta sulla pagina `42`, evitando un 404 o un errore, in attesa che la traduzione venga creata.

**Nessun plugin multilingua attivo**: lo stesso mapping si comporta esattamente come nello Step 2, perche` l'adapter Null restituisce sempre `null` come lingua corrente e il router non tenta nessuna risoluzione aggiuntiva.

## Come un visitatore puo` scegliere la lingua o arrivare direttamente a una lingua specifica

Le URL del Domain Router (subpath/subdomain/external) di solito non portano nessun indizio di lingua che l'adapter possa leggere: per queste URL, la lingua "corrente" secondo il plugin di traduzione e` di solito semplicemente la lingua di default del sito. Senza nient'altro, tutti i visitatori anonimi vedono quindi la stessa lingua, indipendentemente da cosa preferirebbero.

Per questo il router supporta un parametro esplicito, **`kklpm_lang`**, che ha sempre la precedenza sulla lingua rilevata dall'adapter:

```
http://lpmanager.test/promo-lpmanager?kklpm_lang=it
```

Questo forza la risoluzione della traduzione per quella specifica richiesta, indipendentemente da cosa l'adapter avrebbe altrimenti rilevato. Se per quella lingua non esiste nessuna traduzione, il router ripiega comunque sulla pagina sorgente (stesso comportamento sicuro descritto sopra) — non genera mai un errore.

Due usi pratici, entrambi coperti dallo stesso meccanismo:

- **Link diretto a una lingua specifica**: basta condividere/salvare l'URL con il parametro gia` valorizzato.
- **Switcher di lingua manuale**: dato che il contenuto della textarea HTML viene stampato cosi` com'e` (`echo $html_content` in `templates/landing-page.php`, senza `do_blocks()`/`do_shortcode()`), lo switcher nativo di Polylang (blocco Gutenberg o widget) non funziona se incollato li` dentro (vedi discussione precedente). Il modo pratico e supportato oggi e` scrivere a mano due link nella textarea, riusando la stessa URL del mapping (visibile anche tramite il pulsante "Copy link" nella pagina admin del Domain Router):

```html
<a href="/promo-lpmanager?kklpm_lang=it">Italiano</a>
<a href="/promo-lpmanager?kklpm_lang=en">English</a>
```

**Limiti noti di questo meccanismo (scelta deliberata, non un bug):**

- **Nessuna persistenza**: il parametro vale solo per la richiesta che lo contiene. Se il visitatore clicca un altro link sulla stessa pagina senza il parametro, si torna al comportamento normale (rilevamento dell'adapter / lingua di default). Non viene impostato nessun cookie.
- **Canonical silenzioso su richieste con override**: `render_current_request_canonical_tag()` cerca i mapping attivi per la pagina *risolta* (quella tradotta). Se tutti i mapping puntano solo alla pagina sorgente (es. inglese) e il visitatore forza `kklpm_lang=it`, la pagina italiana risultante non ha un proprio mapping — quindi in quella risposta non viene emesso nessun tag canonical custom. Non e` un errore introdotto da questo meccanismo: e` la stessa regola gia` esistente ("nessun mapping attivo per questa pagina -> nessun canonical custom"), solo applicata alla pagina tradotta invece che alla sorgente.
- **Cache di pagina**: molti plugin di cache non mettono in cache le URL con query string, il che di fatto evita per ora il problema di servire una lingua sbagliata da cache — ma non e` garantito per ogni plugin di cache. Una chiave di cache dedicata per lingua resta un lavoro futuro (gia` previsto nello Step 6 di `DEV/AGENTS/PROJECT.md`).

## Conflitto noto con il canonical redirect di Polylang (risolto)

Polylang ha un proprio meccanismo di canonical redirect (`PLL_Canonical::check_canonical_url()`, agganciato su `template_redirect`) che confronta l'URL richiesta con quella che *lui* considera corretta per la lingua della pagina risolta, e reindirizza con 301 se non coincidono. Le URL sintetiche del Domain Router (es. `/promo-lpmanager`) non corrispondono mai alla struttura di permalink nativa di Polylang, quindi questo controllo tentava sempre di reindirizzare verso un path con prefisso di lingua (es. `/it/promo-lpmanager/`) che pero` non corrisponde a nessun mapping — risultato: un 404 reale al posto della pagina tradotta.

Questo accadeva solo per i mapping `subpath` sul dominio principale del sito (quello configurato in Polylang), non per `subdomain`/`external` (host diversi, la stessa logica di Polylang non si applica allo stesso modo).

**Fix**: `KKLPM_Language_Adapter_Polylang::register()` — chiamato una sola volta a request dal resolver quando Polylang e` l'adapter attivo — aggancia il filtro `pll_check_canonical_url` (esposto da Polylang stesso proprio per questo scopo) e restituisce `false` (nessun redirect) quando la richiesta corrente e` gia` stata gestita dal Domain Router (`$wp->matched_rule === KKLPM_Domain_Router_Module::MATCHED_RULE`). Per ogni altra richiesta del sito, il comportamento nativo di Polylang resta invariato.

Nota architetturale: questo e` l'unico punto in cui un adapter fa riferimento a una classe del Domain Router (`KKLPM_Domain_Router_Module::MATCHED_RULE`), un'eccezione deliberata alla regola generale "il Domain Router dipende dagli adapter, mai il contrario" — accettata perche` si limita alla lettura di una singola costante pubblica gia` pensata come marcatore esterno.

Lo stesso tipo di conflitto potrebbe in teoria presentarsi anche con altri plugin di traduzione con una propria logica di canonical redirect (es. WPML): non e` stato verificato ne` corretto per gli altri quattro adapter, in assenza di un caso reale riprodotto.

Verificato sia con i test automatici sia manualmente su un'installazione reale con Polylang attivo, su tutti e tre i tipi di mapping (`subpath`, `subdomain`, `external`).
