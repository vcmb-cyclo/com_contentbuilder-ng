# ContentBuilder NG — sélection des champs Edit et Export

## Règle générale

Publié est l'interrupteur maître d'un élément. Un élément dépublié n'est jamais
affiché, modifié, exposé par API ni exporté, même si une capacité spécialisée
reste enregistrée.

## Template Edit

Le générateur Edit des thèmes Thoth, Dark, Khepri et Blank inclut uniquement
les éléments publiés dont la capacité Edit est active. Chaque élément utilise
les marqueurs label et item. Les éléments non modifiables ne sont pas ajoutés
automatiquement en lecture seule.

Un template non verrouillé peut toujours contenir des marqueurs value ajoutés
manuellement. Un template verrouillé est régénéré selon la sélection Edit à
chaque enregistrement.

## État Default / Modified des éléments Edit

Le badge affiché sous la capacité Edit décrit uniquement les réglages de rendu
de l’élément dans ContentBuilder NG :

- `Default` : le type CB correspond au type éditable natif fourni par la source
  et aucun réglage CB significatif n’est personnalisé ;
- `Modified` : le type natif est remplacé ou au moins une option, valeur par
  défaut, validation, enveloppe ou script propre à CB est configuré.

Pour une source BreezingForms NG, `radiogroup`, `checkboxgroup` et `select`
sont des types natifs au même titre que `text`. Leur simple synchronisation ne
doit donc jamais produire le badge `Modified`. Le calcul utilise
`getEditableElementTypes()` comme référence et reste identique dans la grille
Vue et après l’ouverture de la fenêtre d’options de l’élément.

Cette règle est corrigée dans `6.1.12-RC03`. Les sorties CBStats
`percentage` et `progress`, ainsi que la parité API de `groupset`, restent celles
documentées et introduites dans `6.1.12-RC02`.

## Type de tri avancé

Le réglage historique Type de tri est un réglage avancé propre à chaque
élément. Dans `CB → Vue → Vue`, il est replié derrière une roue dentée placée à
côté du libellé afin de ne pas surcharger la grille. Aucun sélecteur n'est
visible tant que ce contrôle n'est pas ouvert. Lorsqu'un type explicite est
enregistré, un badge discret indique sa valeur.

À partir de `6.1.17-RC1`, le libellé et la roue fermée restent sur une seule
ligne. La roue est un contrôle compact sans grand cadre visible ; seul un fond
discret au survol ou au focus signale son interaction. L'ouverture du réglage
peut développer son panneau sur la ligne suivante. La colonne Export reprend
exactement la largeur et le centrage horizontal des autres colonnes de capacité.

Les valeurs disponibles sont Texte, Date et heure, Date, Heure, Entier et
Décimal. L'absence de valeur conserve la détection automatique. Ce réglage ne
convertit pas et ne modifie pas la donnée enregistrée dans BreezingForms NG ou
dans le stockage CB : il guide le tri et le type produit dans l'export XLSX.

Pour l'export, un type explicite est prioritaire. Texte force une cellule texte,
Entier et Décimal produisent des cellules numériques lorsqu'une valeur est
valide, et les types temporels produisent des cellules Excel de date ou d'heure
pour les formats CB/SQL reconnus. Une valeur incompatible est conservée comme
texte afin d'éviter une conversion destructive.

## Export des éléments

La capacité Export est activée par défaut. Un élément est exporté uniquement
s'il est à la fois publié et activé pour Export. La grille Vue propose une
colonne Export, disponible dans son sélecteur de colonnes, ainsi que les actions
groupées Exporter et Ne pas exporter.

Publié reste l'interrupteur maître du rendu de la colonne Export :

- publié avec Export actif : coche verte cliquable ;
- publié avec Export inactif : croix cliquable ;
- dépublié avec Export mémorisé actif : cadenas non cliquable ;
- dépublié avec Export mémorisé inactif : croix grisée non cliquable.

La publication ou dépublication actualise immédiatement ce rendu. Republier
l'élément restaure sa capacité Export mémorisée sans la modifier.

Les actions d'élément de l'onglet Vue sont enregistrées immédiatement par AJAX.
Après leur réussite, la vue est rechargée à la même position afin de recalculer
les indicateurs d'onglets, l'audit et les templates verrouillés. Le bouton
**Enregistrer** reste donc inactif en l'absence d'autres modifications locales.

Les restrictions de contexte continuent de s'appliquer : ACL, filtres,
recherche, limites CBList, fields CBList et colonnes de menu.

Dans un menu Joomla List View configuré en mode Custom, la case Export est
indépendante de la case Liste. Elle peut retirer un élément de l'export sans le
masquer dans la liste, ou exporter un élément non affiché lorsque la Vue mère
l'autorise. Elle ne peut jamais réactiver un élément dépublié ou dont la
capacité Export est désactivée dans la Vue.

## Colonnes système

Options propose un sélecteur compact et indépendant de l'affichage frontend
pour ID, État et Publié. Les valeurs par défaut des nouvelles vues sont :

- ID : oui ;
- État : non ;
- Publié : non.

Lors de la migration d'une vue existante, chaque choix reprend le réglage
d'affichage correspondant afin de conserver exactement son ancien export.
