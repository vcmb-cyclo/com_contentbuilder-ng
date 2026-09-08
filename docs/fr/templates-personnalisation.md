# Templates et personnalisation

La syntaxe CBStats documentée ici correspond à ContentBuilder NG 6.1.14.

`groupset="ages-fr-FR.ini"` charge des groupes de valeurs réutilisables depuis
les mêmes répertoires sécurisés que `titleset`. Les clés de sa section
`[groups]` acceptent des intervalles inclusifs comme `13-`, `13-17` et `70+`,
ou des valeurs explicites séparées par des virgules comme `1,2,7,9`.
`groups=` reste prioritaire ; `titles=` reste prioritaire sur les libellés du
fichier et sur `titleset=`.

ContentBuilder NG utilise des templates configurés dans chaque vue pour produire les
détails, les formulaires d'édition, les articles et certaines présentations de liste.

## Commencer par un exemple généré

Les plugins de thème peuvent générer :

- un exemple de template détail ;
- un exemple de template éditable ;
- du CSS et du JavaScript associés.

Thèmes livrés :

- Thoth ;
- Dark ;
- Blank ;
- Khepri, hérité de ContentBuilder.

Le thème Thoth est utilisé comme repli lorsque le thème demandé n'est pas
disponible.

Procédure conseillée :

1. sélectionnez un thème ;
2. enregistrez la vue ;
3. générez l'exemple ;
4. testez-le sans modification ;
5. dupliquez le contenu avant une personnalisation importante ;
6. versionnez votre template.

## Variables de champs

Les templates générés utilisent des noms de champs. Les templates d'e-mail emploient
notamment :

```text
{nom:label}     le libellé du champ
{nom:value}     la valeur du champ
{nom:item}      le contrôle de saisie du champ dans un template d'édition
{value}         la valeur brute dans un wrapper de colonne
{value_inline}  la valeur brute dans un wrapper d'article
{webpath nom}   le chemin web absolu d'un fichier envoyé
{CBSite} / {cbsite}   l'URL racine du site
{hide-if-empty nom} ... {/hide}   masque un bloc si le champ est vide
{hide-if-matches nom valeur} ... {/hide-if-matches}   masque un bloc si le champ vaut exactement cette valeur
```

Ces remplacements sont effectués par le service de rendu (`TemplateRenderService`).
Utilisez les exemples générés pour votre vue comme référence prioritaire, car les
champs disponibles dépendent de la source.

## Conditions d'affichage

`{hide-if-empty nom} ... {/hide}` masque le bloc lorsque la valeur du champ est vide.
`{hide-if-matches nom valeur} ... {/hide-if-matches}` masque le bloc lorsque la
valeur courante du champ correspond exactement à `valeur`.

Dans les templates Détail, ces conditions s'appliquent aux valeurs affichées.
Dans les templates Édition, elles s'appliquent aussi aux blocs en lecture seule
utilisant `{nom:value}`. En revanche, un bloc contenant `{nom:item}` reste affiché
même si la valeur est vide ou correspond à `hide-if-matches`, afin de permettre la
saisie ou la correction du champ.

## Exemple simple d'e-mail

```html
<p>Nouvelle demande :</p>
<p><strong>{nom:label}</strong> : {nom:value}</p>
{hide-if-empty message}
<p><strong>{message:label}</strong> : {message:value}</p>
{/hide}
```

## Préparation PHP

Les onglets Détail et Édition comportent une zone de préparation exécutée avant le
rendu. L'interface fournit des exemples et des sélecteurs de snippets.

Risques :

- erreur PHP rendant la vue inaccessible ;
- exposition de données sensibles ;
- incompatibilité après modification d'un champ ;
- ralentissement si le code effectue des requêtes répétées ;
- contournement involontaire de l'échappement HTML.

Réservez cette fonction aux administrateurs techniques. Testez sur une copie du site.

## Wrappers de colonnes

Une colonne de liste peut appliquer un wrapper autour de sa valeur. Les fichiers de
langue donnent trois catégories d'usage :

- HTML avec `{value}` ;
- code PHP transformant `$value` ;
- balise de plugin de contenu.

Exemple HTML :

```html
<strong>{value}</strong>
```

N'insérez jamais directement une valeur non fiable dans un attribut HTML ou du
JavaScript sans échappement adapté.

## Plugins de contenu

Balises détectées :

```text
{CBList ...}
{CBDownload ...}
{CBImageScale ...}
{CBRating ...}
{CBVerify ...}
{CBStats ...}
```

### Intégrer une vue liste avec CBList

Le plugin de contenu `CBList` intègre une vue liste interactive complète
ContentBuilder NG dans un article Joomla. La vue conserve ses droits ACL, ses
filtres, sa pagination et ses actions sur les enregistrements.

Exemple simple — affiche la vue 15 avec ses champs, son titre, son tri, sa
pagination, ses actions, sa mise en page et son thème configurés, sans aucune
personnalisation CBList :

```text
{CBList id=15}
```

Exemple réaliste avec les principales options :

```text
{CBList id=15 fields="Nom|Prenom|Email" labels="title=Liste des inscrits" sort="Nom|Prenom" dir="asc" pagination=25 limit=10 actions="detail|edit|export" layout=cards height=700 loading=lazy}
```

Pour `fields` et `sort`, utilisez uniquement les noms source exacts des éléments,
en respectant la casse, ou leurs identifiants de référence exacts. Les libellés
affichés et les variantes d’accent ou de casse sont invalides : si le nom source
est `Prenom`, `Prénom` et `prenom` sont refusés.
Pour `fields`, un élément existant indiqué par son nom exact reste valide lorsqu’il
est dépublié ou désactivé pour la liste. ContentBuilder omet simplement cette
colonne : cet état n’est pas une erreur de syntaxe. Les champs utilisés par `sort`
doivent toujours être des colonnes visibles de la liste.

`id` identifie la vue ContentBuilder NG et reste obligatoire ; les exemples du
projet utilisent la vue `15`. `fields` filtre et ordonne les colonnes affichées.
La clé `title` de `labels`, par exemple `labels="title=Liste des inscrits"`,
remplace le titre visible de la vue et fournit le titre de la Card avec `card=`.
Omettez `labels` pour conserver le titre configuré. Utilisez `hide="title"` pour
masquer l’un ou l’autre titre.
`sort` définit le tri initial :
une seule valeur `dir="asc"` ou `dir="desc"` s’applique à tous les champs triés.
Indiquez une direction par champ, séparée par `|`, uniquement pour mélanger les
directions. `pagination` fixe le nombre d’enregistrements par page. `limit`
conserve au maximum les N premiers enregistrements accessibles après les ACL,
la recherche, les filtres et le tri effectif. Ce n’est pas une pagination : la
pagination et les exports portent sur le sous-ensemble limité, et le total
affiché ne révèle pas les enregistrements situés au-delà. `actions`
sélectionne les contrôles disponibles dans la limite des ACL. `layout`, `height`
et `loading` règlent la présentation et le chargement du cadre.

Les options numériques `id`, `height`, `pagination` et `limit` doivent être
écrites sans guillemets : utilisez `limit=10`, et non `limit="10"`.

- `layout=cards` affiche les enregistrements sous forme de cartes plutôt que
  dans le tableau standard. C’est l’alias lisible de la mise en page `listcard`.
  Sans `layout`, la mise en page normale de la vue est conservée.
- `height=700` fixe la hauteur minimale initiale du cadre à 700 pixels. Le cadre
  peut ensuite s’agrandir automatiquement si la liste a besoin de plus de place.
- `loading=lazy` est la valeur par défaut : la liste est chargée lorsqu’elle
  approche de la partie visible de la page. `loading=eager` la charge immédiatement.
- CBList utilise le thème configuré dans la vue ContentBuilder NG. **Thoth** est
  le thème par défaut et de secours ; la balise CBList ne possède pas d’option
  `theme=`.

CBList et CBStats peuvent être associés dans le même article, par exemple un
graphique de synthèse suivi de la liste détaillée des inscrits :

```text
{CBStats id=15 field=Ville output=bar}
{CBList id=15 fields="Nom|Prenom|Email" labels="title=Liste des inscrits" sort="Nom|Prenom" dir="asc"}
```

CBStats insère dans les contenus Joomla des statistiques dynamiques provenant
d'une vue ContentBuilder NG. Sa syntaxe générale est :

```text
{CBStats id=IdVue ...}
```

Exemples :

```text
{CBStats id=15 output=total}
{CBStats id=15 output=remaining target=200}
{CBStats id=15 field=Civilite value="H" output=percentage}
{CBStats id=15 output=progress target=200}
{CBStats id=15 output=view_name}
{CBStats id=15 field=Parcours output=table}
{CBStats id=15 field=Parcours output=json sort=title dir=asc}
{CBStats id=15 field=Parcours output=pie sort=value dir=desc}
{CBStats id=15 field=Parcours output=bar sort=value dir=desc}
{CBStats id=15 field=Age output=avg}
{CBStats id=15 field=Age output=histogram groups="18-29;30-39;40-49;50+"}
{CBStats id=15 field=DateInscription output=line sort=title dir=asc limit=30}
{CBStats id=15 field=Age output=radar groups="18-29;30-39;40-49;50+"}
{CBStats id=15 field=Parcours output=pie labels="title=👥 Total des inscrits" export=manual}
{CBStats id=15 field=Catégorie output=pie add="Existant=-2;Externe=3"}
{CBStats id=15 field=Catégorie output=table titles="1=Groupe 1;2=Groupe 2"}
{CBStats id=15 field=Catégorie output=bar add="1=-2;2=3" titles="1=Groupe 1;2=Groupe 2" sort=value dir=desc}
{CBStats id=15 field=Departement value="78|60" output=distinct}
{CBStats id=15 field=Parcours output=sum}
{CBStats id=15 field=Parcours output=min}
{CBStats id=15 field=Parcours output=max}
{CBStats id=15 filter[field]=Statut filter[value]="Ouvert" output=total}
{CBStats id=15 filter[field]=Statut filter[value]="Ouvert*" output=total}
{CBStats id=15 filter[field]=Statut filter[value]="Ouvert* | En attente" output=total}
{CBStats id=15 filter[field]=Statut filter[value]="Ouvert" output=remaining target=200}
{CBStats idsum=15+27 field="Parcours" output="table" labels="title=Monticyclo / Montigravel"}
{CBStats idsum=31+32+33+34+35 field="Distance" output="bar" labels="title=BRM"}
```

### Fusionner des vues avec `idsum`

Utiliser `idsum=15+27` à la place de `id=` pour additionner les statistiques
de deux à cinq vues ContentBuilder NG. Les identifiants sont des entiers
positifs uniques séparés par `+` ; `id` et `idsum` ne peuvent pas être combinés.
`field=` est obligatoire pour tous les outputs `idsum`, y compris
`output=total`.

Chaque vue applique d’abord ses permissions STATS et de champ, ses filtres et
son regroupement habituel. Les nombres associés aux libellés exactement
identiques sont ensuite additionnés. Un libellé présent dans une seule vue est
conservé. Le traitement se poursuit une seule fois sur les données fusionnées
avec `add`, la normalisation à zéro, `titles`, le tri final et l’output. Le total
est la somme des catégories fusionnées finales.

Les identifiants invalides ou dupliqués, moins de deux ou plus de cinq vues, une
vue absente ou inaccessible, un champ absent et `output=view_name` sont refusés.
Les identifiants dupliqués sont rejetés afin d’éviter tout double comptage.

### Export manuel figé

Ajoutez `export=manual` à une balise Pie, Bar ou Table pour afficher les libellés, valeurs et total finaux ainsi qu’une balise `source=manual` visible. Les filtres, ajouts, renommages et tris sont déjà intégrés aux données figées. Le bouton centré copie exactement la syntaxe affichée, prête à être collée dans un autre article sans dépendre de la vue d’origine.

| Sortie | Résultat | `field` obligatoire |
| --- | --- | --- |
| `total` | Nombre d'enregistrements correspondants | Non |
| `view_name` | Nom de la vue ContentBuilder NG | Non |
| `table` | Tableau HTML statique valeur/nombre | Oui |
| `json` | Tableau JSON brut d'objets `{label,value}` | Oui |
| `pie` | Graphique Pie responsive | Oui |
| `bar` | Graphique à barres horizontal responsive | Oui |
| `histogram` | Histogramme vertical responsive | Oui |
| `line` | Courbe responsive utilisant les comptes normalisés | Oui |
| `radar` | Radar responsive de 3 à 8 axes | Oui |
| `sum` | Somme pondérée des valeurs numériques | Oui |
| `min`, `max` | Plus petite et plus grande valeur numérique | Oui |
| `avg` | Moyenne arithmétique des valeurs numériques retenues | Oui |

`table`, `json`, `pie`, `bar`, `histogram`, `line` et `radar` consomment les mêmes
données PHP normalisées. Un tableau vide affiche `0` ; un graphique vide affiche
un message localisé. Histogram est vertical, Line conserve les catégories dans
l’ordre final et Radar exige de 3 à 8 axes (4 à 6 sont recommandés). `avg` ignore
les valeurs vides ou non numériques et calcule la moyenne des valeurs
individuelles. JSON ne possède aucune enveloppe HTML ou JavaScript :

```json
[
  {"label":"Valeur A","value":12},
  {"label":"Valeur B","value":7}
]
```

Utilisez ensemble `filter[field]=NomDuChamp` et `filter[value]="Valeur"`. Sans
joker, `filter[value]="Ouvert"` correspond uniquement à la valeur exacte. Avec
`filter[value]="Ouvert*"`, des valeurs comme `Ouvert` et `Ouvert (externe)`
peuvent correspondre. Le caractère `|` sépare les alternatives et les espaces
de début et de fin sont supprimés. Dans une balise d'article,
`field=NomDuChamp value="Valeur"` sert aussi de raccourci de filtre lorsque
`filter[field]` est absent.

Le champ regroupé et le champ filtré peuvent être différents :

```text
{CBStats id=15 field=Element-1 filter[field]=Element-2 filter[value]="Dét* | 3 | 4" output=bar}
```

Ici, `field=Element-1` est regroupé et affiché, tandis que
`filter[field]=Element-2` sert uniquement à sélectionner les enregistrements.
`*` est un joker, `|` sépare les alternatives et les espaces autour des valeurs
sont ignorés. Sans joker, la comparaison est exacte.

Lorsque le filtre porte sur le champ affiché, le raccourci suivant est strictement
équivalent au filtre complet sur `Element-2` :

```text
{CBStats id=15 field=Element-2 value="Dét* | 3 | 4" output=bar}
```

`value=` est réservé à ce raccourci sur le même champ. Ne le confondez pas avec
`values=`, utilisé exclusivement par `source=manual`.

Les sorties de statistiques de champ acceptent `sort=none|title|value` et
`dir=asc|desc`. Les valeurs par défaut sont `sort=none` et `dir=asc`.
`sort=none` conserve l'ordre naturel du moteur ; `sort=title` applique un ordre
naturel des libellés selon la langue active ; `sort=value` compare les nombres.
`dir` modifie la direction du tri choisi.

Pour `table`, `json`, `pie`, `bar`, `histogram`, `line` et `radar`, `add="Libellé=EntierSigné"` applique des
deltas cumulatifs : une valeur positive ajoute, zéro ne modifie rien et une
valeur négative retire des occurrences. Si le résultat final calculé devient
négatif, CBStats utilise temporairement `0` pour ce libellé avant le tri, le
calcul des pourcentages et le rendu ; les données sources restent inchangées et
un résultat ultérieur nul ou positif est utilisé normalement.
`titles="Original=Titre affiché"` modifie uniquement l'affichage, sans changer
les données sources ni fusionner les catégories. Les libellés non indiqués
restent inchangés. L'ordre est données, filtres, regroupement, `add`, `titles`,
tri, puis output ; `sort=title` utilise les titres affichés finaux. Les
points-virgules séparent les entrées et le premier signe égal sépare chaque paire.

Pie, Bar, Histogram, Line et Radar utilisent les mêmes données normalisées,
infobulles et textes graphiques localisés. Les graphiques sont responsives et
peuvent coexister dans toute combinaison sur une page. Utilisez
`groups="18-29;30-39;40-49;50+"` pour des groupes par intervalles inclusifs,
ou `groups="1,2,7,9=Groupe 1;3,4,8=Groupe 2"` pour des valeurs explicites non contiguës,
`output=line sort=title dir=asc` pour une séquence de dates/catégories et
`output=radar` pour comparer de 3 à 8 dimensions.

`sum`, `min` et `max` retournent `0` lorsque les valeurs correspondantes sont
vides ou ne sont pas toutes numériques. Les champs de date peuvent fournir un
`min` et un `max` chronologiques, tandis que `sum` reste à `0`. Toutes les sorties
basées sur un champ vérifient sa disponibilité API/Stats.

CBStats applique toujours la permission STATS de la vue. Pour l'URL/API, vérifiez
les réglages **API + Droits**, la disponibilité API/Stats des champs et l'onglet
**API** de la vue. Les outputs URL disponibles sont `json`, `table`, `pie`, `bar`,
`histogram`, `line`, `radar`, `total`, `distinct`, `sum`, `min`, `max`, `avg` et `view_name` ;
les sorties de liste acceptent aussi `add`, `titles`, `sort`, `dir`, `groups` et
`limit`. Dans les articles Joomla, CBStats signale ensemble toutes les erreurs
de syntaxe indépendantes de la balise, précise le paramètre et la valeur concernés
et n'affiche aucune statistique tant que la balise est invalide. Le bloc d'erreur
contient un lien vers l'aide publique localisée de la syntaxe CBStats, ouverte dans
un nouvel onglet. Les erreurs de droits d'accès et les erreurs internes inattendues
restent génériques. `debug=1` demande un
diagnostic uniquement lorsque DEBUG est activé sur la vue ContentBuilder NG
ciblée ; il n'accorde aucun accès et ne modifie jamais les permissions de vue, de
champ ou STATS.

La syntaxe complète des plugins Download, ImageScale et Verify n'est pas documentée
de façon exhaustive dans les guides du dépôt : **À vérifier** à partir des templates
historiques utilisés sur votre site.

## Overrides Joomla

Les layouts frontend se trouvent dans `site/tmpl/<vue>/` dans le dépôt source
(installés sous `components/com_contentbuilderng/tmpl/`). Pour une personnalisation de
site, préférez le mécanisme d'override du template Joomla lorsque le layout s'y prête,
au lieu de modifier les fichiers installés du composant.

Layouts de liste livrés (vue `list`) :

- `default` (tableau) ;
- `listcompact` ;
- `listcard` ;
- `listtiles` ;
- `listone`, `listtwo`, `listthree`.

Le chemin d'override Joomla standard est :

```text
templates/<votre_template>/html/com_contentbuilderng/list/default.php
```

> ℹ️ **Note :** l'écran Joomla **Système > Templates de site > [votre template] >
> Créer des substitutions** liste les vues du composant et copie le layout choisi au
> bon emplacement. Le chemin précis dépend du nom de la vue (`list`, `details`,
> `edit`, `latest`, `publicforms`) et du layout — *à vérifier* dans votre installation.

## Ce qu'il ne faut pas modifier directement

Évitez de modifier :

- les fichiers sous `components/com_contentbuilderng` ;
- les fichiers sous `administrator/components/com_contentbuilderng` ;
- les plugins livrés ;
- les dépendances sous `vendor` ;
- les tables SQL à la main sans diagnostic.

Une mise à jour peut remplacer ces fichiers.

## Bonnes pratiques

- gardez une copie du template avant modification ;
- utilisez des noms de champs stables ;
- échappez les valeurs affichées ;
- limitez le PHP ;
- testez les champs vides ;
- testez les uploads ;
- testez avec un utilisateur non administrateur ;
- vérifiez le rendu mobile ;
- contrôlez le mode sombre si le thème Dark est utilisé ;
- désactivez le Debug après validation.

> 📷 *Capture à ajouter : génération d'un template exemple et éditeur de préparation PHP — `docs/fr/img/templates-preparation.png`*
### Limiter les valeurs CBStats et masquer des éléments

Utilisez `limit` après un tri existant pour ne conserver que les premières
valeurs statistiques. Utilisez `hide` avec `title`, `total`, `values` ou `graph`,
séparés par `|`. `title` masque le titre du bloc ou de la Card défini par
`labels="title=..."`, `total` masque le Total affiché et `values` masque la liste
textuelle des libellés et valeurs sous le graphique sans modifier le graphique,
et `graph` masque le dessin tout en conservant cette liste textuelle légère :

```text
{CBStats id=15 field="Ville" output="table" sort="value" dir="desc" limit=10}
{CBStats id=15 field="Ville" output="table" labels="title=Villes" hide="title" card=v1}
{CBStats idsum=15+27 field="Club" output="bar" sort="value" dir="desc" limit=10 hide="total"}
{CBStats id=15 field="Age" output="histogram" hide="total|values"}
{CBStats id=15 field="Age" output="radar" hide="graph|total"}
```

Le total affiché et les pourcentages des graphiques sont recalculés sur les
valeurs conservées par `limit`. Le masquage ne modifie ni les calculs, ni les
ACL, ni les filtres. Masquer les trois éléments est refusé. L’ancienne syntaxe
`total=hide` n’est plus prise en charge ; utilisez `hide="total"`.

### Cards éditoriales pour contenu libre

Utilisez un `div` HTML standard, compatible avec les éditeurs, pour regrouper du
HTML libre ainsi que des balises CBStats et CBList avec le rendu partagé des
Cards. La syntaxe complète est recommandée :

```html
<div class="cb-cards">
  <div class="cb-card-editorial" data-card="v1" data-w="33">
    <h4 data-cb-card-title>Informations</h4>
    <p>Total : {CBStats id=15 output=total}</p>
    <p>Groupes distincts : {CBStats id=15 field=Groupe output=distinct}</p>
    {CBList id=15 fields="Nom|Prenom" limit=5}
  </div>
</div>
```

Le titre visible `<h1>` à `<h6 data-cb-card-title>` devient le bandeau coloré
de la Card et reste modifiable dans l’éditeur visuel. Un titre Hx sans cet
attribut reste dans le corps. L’ancienne syntaxe `data-title` reste acceptée
et conserve ses suffixes H1 à H6 et rem positif. Sans l’un de ces titres,
aucun bandeau n’est généré. `data-card` utilise
`v1` par défaut et `data-w` utilise `33` par défaut ; les largeurs acceptées
sont `33`, `66` et `100`. Les espaces vides et insécables insérés entre les
Cards par un éditeur sont ignorés dans les grilles `cb-cards`.

Une liste de définitions rend les chiffres clés plus lisibles dans toutes les
variantes H1 à H6 et V1 à V6. `<dt>` contient le libellé et `<dd>` sa valeur :

```html
<dl class="cb-kpi-list">
  <div>
    <dt>Inscriptions</dt>
    <dd>{CBStats id=15 output=total}</dd>
  </div>
  <div>
    <dt>Places restantes</dt>
    <dd>{CBStats id=15 output=remaining target=200}</dd>
  </div>
</dl>
```

La grille `.cb-cards` passe automatiquement à une colonne sous 768 px.

### Jeux de titres CBStats réutilisables

Utilisez un fichier INI administré lorsque plusieurs statistiques partagent
les mêmes renommages :

```text
{CBStats id=15 field=Departement titleset="departements-fr-FR.ini" output=table}
```

Les fichiers personnalisés de `media/contentbuilderng/cbstats/titlesets/`
surchargent les fichiers fournis de même nom. Les correspondances `titles=`
écrites dans la balise restent prioritaires. Un fichier absent ou invalide
laisse les valeurs originales visibles et produit seulement un Warning lorsque
le Debug Joomla est activé.

L’éditeur est accessible dans **ContentBuilder NG → À propos → Actions → Jeux
de titres CBStats**. Un clic sur le nom affiche le contenu. Les fichiers fournis
restent en lecture seule et peuvent être dupliqués dans le répertoire du site ;
les fichiers propres au site sont modifiables. Le sélecteur de colonnes de la
liste mémorise les colonnes masquées dans le navigateur.
La liste permet aussi de rechercher un titre de fichier et de trier les
colonnes de données.
Dans l’éditeur, l’extension `.ini` du nom de fichier est facultative. Les
actions Valider et Enregistrer affichent leur résultat, et l’abandon de
modifications non enregistrées demande confirmation.
La recherche porte sur le nom et le titre du fichier. La liste affiche 10
fichiers par défaut, avec les choix 5, 10, 25, 50 ou Tous. L’origine est indiquée
par « CBStats » ou « Site » et les titres longs sont limités à deux lignes.
La liste utilise la sélection et le menu Actions Joomla. Les fichiers CBStats
peuvent être dupliqués ; les fichiers Site peuvent être modifiés, copiés ou
supprimés. « Enregistrer une copie » génère `nom-copy.ini`, puis
`nom-copy-2.ini`. Le champ Langue suggère les langues Joomla installées tout en
acceptant librement un code comme `it-IT`.
Les jeux sélectionnés peuvent être exportés dans un fichier `.ini` ou, pour une
sélection multiple, dans une archive `.zip`. L’import accepte plusieurs
fichiers `.ini` de 1 Mo maximum chacun. Tous sont validés avant installation et
aucun fichier Site existant n’est écrasé.
La sélection Joomla active notamment Dupliquer et Exporter pour les fichiers
CBStats. Le champ Langue associe un sélecteur visible des langues installées à
la saisie libre du code. Les erreurs de validation identifient directement le
nom de fichier, le titre ou les entrées concernés.
