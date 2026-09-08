# Charte graphique frontend — ContentBuilder NG

## Référence validée pour 6.1.16-RC1

Le format des boutons **Éditer / Imprimer** dans Détail est la référence pour
les barres d’actions des écrans Liste, Détail et Éditer. L’harmonisation porte
sur la typographie et les marges, pas uniquement sur la couleur au survol.

## Typographie et dimensions

- Police héritée du template Joomla ; aucune police imposée par CB.
- Taille : `--cb-font-size-compact`, soit `0.85rem`.
- Graisse : `600`.
- Marges intérieures : `--cb-button-padding-block` (`0.34rem`) et
  `--cb-button-padding-inline` (`0.85rem`).
- Boutons autonomes arrondis ; conserver les jonctions natives du groupe
  Recherche / Réinitialiser, sans arrondir séparément ses boutons.
- Ces règles sont partagées dans `media/css/frontend.css`, sans copie de
  valeurs différente pour Nouveau, Export XLS ou les actions de Détail.

Les boutons concernés sont Nouveau, Supprimer, Export XLS, Rechercher,
Réinitialiser, Imprimer, Éditer, Retour, Enregistrer, Appliquer et les paramètres
d’article, lorsqu’ils sont rendus dans les barres d’actions. La pagination, les
liens de navigation ordinaires et les contrôles internes des champs ne sont pas
transformés en boutons de barre d’actions.

## Couleurs et interaction

| Actions | Style |
| --- | --- |
| Nouveau, Imprimer, Éditer, Retour, Réinitialiser | Fond transparent au repos, texte et contour gris, survol gris natif |
| Enregistrer, Appliquer, Paramètres de l’article | Même style neutre ; aucun fond rouge, bleu ou bleu-vert ajouté |
| Supprimer | Conserver le contour rouge et le comportement destructif |
| Export XLS | Conserver le style vert d’export ; typographie et marges communes |
| Rechercher | Conserver la couleur d’action existante ; typographie et marges communes |

Le style neutre utilise `btn btn-sm btn-outline-secondary`. Ne pas le remplacer
par `btn-primary` : certains templates définissent cette classe en rouge.
Ne pas ajouter de couleur spécifique basée sur `--link-color` pour Éditer.
Les couleurs exactes, transitions, états actifs, désactivés et focus restent
ceux du template et du thème CB. Le focus clavier doit rester visible.

## Mise en page à préserver

- Le champ où l’on saisit la recherche conserve une largeur de **180 px**.
- Sur écran assez large, conserver les contrôles de la barre sur la même ligne.
- Sur petit écran, autoriser le retour à la ligne sans débordement horizontal.
- Dans les options administrateur, Imprimer reste à côté d’Export XLS.
- Le bloc Tri précède les menus référents ; ses listes utilisent
  `form-select form-select-sm`. Son cadre conserve un padding de 8 px (`p-2`)
  et les lignes un espacement de 4 px (`gap-1`, `mb-1`).

## Vérification avant validation

Comparer les styles calculés des boutons à Éditer/Imprimer : famille de police,
taille, graisse, hauteur de ligne et padding. Vérifier le repos, le survol, le
focus clavier et l’état désactivé, ainsi que les écrans Liste, Détail et Éditer.
Contrôler les couleurs fonctionnelles de Supprimer et Export XLS, la largeur de
recherche et l’absence de débordement. Un test sur un fragment HTML ou sur un
seul template ne vaut pas validation de tous les templates et thèmes CB.

Les captures et scripts intermédiaires restent dans `qa-artifacts/` ; `build/`
ne contient que les ZIP locaux. Cette charte est interne au dépôt et n’entre
pas dans les paquets installables.
