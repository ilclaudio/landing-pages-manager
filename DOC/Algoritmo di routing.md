# Come avviene il routing

Questo documento descrive **come funziona oggi il routing nel plugin**, seguendo l'implementazione reale attuale.

L'idea chiave e` questa:

1. il plugin puo` **intercettare la richiesta WordPress molto presto** tramite l'hook `parse_request`;
2. se trova un mapping valido, **riscrive la richiesta** in modo che WordPress carichi una specifica pagina;
3. solo piu` tardi, quando WordPress sceglie il template, il plugin puo` **sostituire il template della pagina** con `templates/landing-page.php` tramite `template_include`.

In altre parole:

- il **Domain Router** decide **quale pagina** deve essere servita;
- il **Landing Page Module** decide **con quale template** quella pagina deve essere renderizzata.

## Schema rapido

```text
Richiesta HTTP
  |
  v
Web server / WordPress bootstrap
  |
  v
Plugin bootstrap
  - registra parse_request
  - registra template_include
  |
  v
Hook parse_request
  |
  +-- richiesta admin / AJAX / REST? -> bypass, nessun routing custom
  |
  +-- altrimenti:
        legge host + path
        cerca mapping attivo
        |
        +-- nessun match -> WordPress continua normalmente
        |
        +-- match con target invalido -> forza stato 404
        |
        +-- match valido -> imposta query_vars per la pagina mappata
  |
  v
WordPress risolve la query principale
  |
  v
Hook template_include
  |
  +-- pagina con landing page abilitata? -> usa templates/landing-page.php
  |
  +-- altrimenti -> usa il template normale del tema
```

## 1. Bootstrap del plugin

Il file principale `landing-pages-manager.php`:

- carica le classi del plugin;
- registra l'activation hook;
- esegue `KKLPM_Plugin::bootstrap()`.

`KKLPM_Plugin::bootstrap()` istanzia e registra tre moduli:

- `KKLPM_Landing_Page_Module`
- `KKLPM_Domain_Router_Module`
- `KKLPM_Domain_Router_Admin_Page`

Per il routing frontend, i due punti importanti sono:

- `KKLPM_Domain_Router_Module::register()` registra `parse_request`;
- `KKLPM_Landing_Page_Module::register()` registra `template_include`.

Questa separazione e` importante: il plugin **non fa tutto in un solo hook**.

## 2. Quando il plugin intercetta davvero una richiesta

L'intercettazione runtime avviene in:

- `KKLPM_Domain_Router_Module::handle_parse_request()`

Questo metodo gira sull'hook `parse_request`, cioe` **prima che la query principale di WordPress venga eseguita**.

Prima di fare qualunque match, il modulo verifica se la richiesta va ignorata:

- `is_admin()` -> area amministrativa, nessun routing custom
- `wp_doing_ajax()` -> richiesta AJAX, nessun routing custom
- `REST_REQUEST` -> chiamata REST API, nessun routing custom

Se uno di questi casi e` vero, il plugin esce subito e lascia WordPress lavorare normalmente.

## 3. Quali dati usa per decidere il routing

Se la richiesta non viene bypassata, il plugin legge:

- l'host da `$_SERVER['HTTP_HOST']`
- il path da `$wp->request`

Poi normalizza i valori:

- l'host viene convertito in minuscolo, ripulito dalla porta finale e dai punti superflui;
- il path viene normalizzato con slash coerenti, ad esempio `promo/` diventa `/promo`.

La logica di normalizzazione e matching e` concentrata in:

- `KKLPM_Domain_Router_Matcher`

## 4. Dove cerca i mapping

I mapping stanno nella tabella custom:

- `{prefix}kklpm_landing_domain_map`

Il plugin non carica tutta la tabella per ogni richiesta. Fa invece una query mirata tramite:

- `KKLPM_Domain_Map_Repository::find_request_candidates()`

Questa query cerca solo mapping attivi che potrebbero combaciare con:

- `subdomain` sul valore dell'host
- `external` sul valore dell'host
- `subpath` sul valore del path

Quindi il plugin ottiene una lista ristretta di candidati e poi applica il match finale in PHP con:

- `KKLPM_Domain_Router_Matcher::match_request()`

## 5. Come viene deciso il match

Il match attuale e` **esatto**, non parziale e non gerarchico.

Regole attuali:

- `subpath` matcha quando il path normalizzato della richiesta e` uguale al valore salvato
- `subdomain` matcha quando l'host normalizzato della richiesta e` uguale al valore salvato
- `external` matcha allo stesso modo del `subdomain`, cioe` per uguaglianza esatta dell'host

Non c'e` ancora:

- matching avanzato per prefissi o wildcard
- supporto lingua nella risoluzione runtime
- logica speciale per `X-Forwarded-Host`

## 6. Cosa succede dopo un match

Dopo il match, `resolve_target_page_id()` distingue tre casi.

### Caso A: nessun mapping trovato

Il metodo restituisce `null`.

Effetto:

- il plugin non tocca la richiesta;
- WordPress continua con il suo flusso normale.

### Caso B: mapping trovato ma target non valido

Il mapping esiste, ma la pagina target:

- non esiste,
- non e` di tipo `page`,
- oppure e` nel cestino.

Il metodo restituisce `false`.

Effetto:

- il plugin chiama `apply_not_found()`;
- imposta `error=404`;
- marca comunque la richiesta come passata dal router con `matched_rule = kklpm-domain-router`.

Quindi il plugin non reindirizza altrove: forza direttamente uno stato di richiesta non trovata.

### Caso C: mapping valido

Se il target e` una pagina valida, il plugin chiama `apply_page_request()`.

Qui modifica il `WP` object principale impostando:

- `query_vars['page_id']`
- `query_vars['pagename']`
- `query_vars['post_type'] = 'page'`
- `query_string`
- `request`
- `matched_rule`
- `matched_query`
- `did_permalink = true`

In pratica, il plugin fa credere a WordPress che la richiesta corrente debba essere risolta come quella pagina.

Non c'e` un redirect HTTP visibile: l'URL nel browser resta quello richiesto originariamente.

## 7. Protezione dalle collisioni sui subpath

Per i mapping `subpath` esiste una protezione importante.

Se il path richiesto corrisponde gia` a un contenuto nativo di WordPress, il plugin **non deve rubare la richiesta**.

Per questo `is_colliding_subpath_mapping()` controlla:

- `get_page_by_path()` per vedere se esiste gia` una pagina con quello slug/percorso
- `url_to_postid( home_url( $normalized_path ) )` per intercettare anche altri contenuti pubblici quando i permalink sono attivi

Se trova un contenuto esistente diverso dalla pagina target del mapping:

- il routing custom viene annullato;
- la richiesta resta nelle mani di WordPress.

Questa regola oggi vale solo per `subpath`.

## 8. Cosa succede dopo parse_request

Se `parse_request` ha impostato la pagina target, WordPress prosegue normalmente con la query principale usando i nuovi `query_vars`.

A questo punto il plugin **non ha ancora deciso il template finale**.

La decisione del template arriva dopo, tramite:

- `KKLPM_Landing_Page_Module::filter_template_include()`

## 9. Come il plugin decide il template finale

Nel filtro `template_include`, il plugin controlla:

- se l'oggetto corrente e` una `page` singola;
- se su quella pagina il meta `_kklpm_landing_enabled` e` attivo.

Se la landing page e` abilitata:

- restituisce `templates/landing-page.php`

Se non e` abilitata:

- restituisce il template originale del tema

Questo significa che il Domain Router e la Landing Page sono collegati, ma restano due livelli distinti:

- il router puo` mappare la richiesta verso una pagina WordPress;
- solo quella pagina decide poi se usare il template isolato del plugin oppure il template del tema.

## 10. Cosa rende `templates/landing-page.php`

Se il template custom viene scelto:

- legge HTML, CSS e JavaScript salvati nei post meta;
- legge anche i toggle:
  - show theme header/footer
  - load WordPress CSS/JS

Poi ha due modalita`:

- se `Show theme header/footer` e` attivo, usa la struttura del tema;
- altrimenti renderizza un documento HTML isolato del plugin.

Questa parte non cambia piu` la pagina servita: cambia solo **come viene renderizzata**.

## 10.1 Come viene gestito il canonical URL

Se la pagina corrente ha almeno un mapping attivo nel Domain Router, il plugin prova a emettere anche un canonical URL.

Regole attuali:

- se esiste un mapping attivo marcato come canonico per quella pagina, il canonical punta a quel mapping;
- se esistono mapping attivi ma nessuno e` marcato come canonico, il plugin usa come fallback il permalink nativo della pagina su `home_url()`;
- se la pagina non ha mapping attivi, il plugin non emette alcun canonical custom.

Il tag viene emesso in due modi, a seconda del rendering:

- tramite hook `wp_head`, quando il template usa `wp_head()`;
- direttamente dentro `templates/landing-page.php`, quando il template isolato non carica `wp_head()`.

Questa doppia gestione evita di perdere il canonical nelle landing page piu` isolate.

## 11. Sequenza completa, passo passo

1. Arriva una richiesta HTTP a WordPress.
2. WordPress carica il plugin e i suoi moduli.
3. Il plugin ha gia` registrato `parse_request` e `template_include`.
4. Su `parse_request`, il Domain Router decide se ignorare la richiesta oppure analizzarla.
5. Se la analizza, legge host e path correnti.
6. Cerca candidati attivi nella tabella dei mapping.
7. Applica il match esatto in base al tipo di mapping.
8. Se non trova nulla, lascia tutto invariato.
9. Se trova un mapping invalido, forza `404`.
10. Se trova un mapping valido, riscrive la richiesta verso la pagina target.
11. WordPress continua la query principale usando quella pagina.
12. Su `template_include`, il Landing Page Module controlla se la pagina ha la landing attiva.
13. Se si`, usa `templates/landing-page.php`; altrimenti lascia il template del tema.
14. Se la pagina ha mapping attivi, il plugin prova anche a emettere il canonical corretto.
15. Il browser continua a mostrare l'URL originario richiesto.

## 12. Limiti attuali del routing

In questo momento il routing implementato ha questi limiti o gap dichiarati:

- nessun adapter multilingua attivo nel runtime
- nessun supporto a wildcard o matching parziale
- nessuna gestione speciale di proxy / `X-Forwarded-Host`
- nessuna cache dedicata per host/lingua

Quindi il comportamento attuale e` volutamente semplice:

- hook precoce su `parse_request`
- match esatto host/path
- riscrittura della query verso una pagina WordPress
- eventuale sostituzione del template in `template_include`
