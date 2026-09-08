# Politique de compatibilité PHP

## Portée

Cette politique s'applique à ContentBuilder NG pour Joomla 6. Le composant ne
doit employer aucune syntaxe ou API exigeant une version supérieure au minimum
supporté.

## Niveaux de support

| Version | Statut | Validation obligatoire |
| --- | --- | --- |
| PHP 8.3 | Supporté / Production | Syntaxe et suite PHPUnit complètes |
| PHP 8.4 | Supporté / Production | Syntaxe, PHPUnit, couverture, PHPStan, packaging et smoke test Joomla |
| PHP 8.5 | Compatibilité testée / Expérimental | Syntaxe et suite PHPUnit complètes avec les extensions requises |

PHP 8.4 est l'environnement standard de développement et la cible principale
de validation. PHP 8.3 demeure le plancher de compatibilité : une fonctionnalité
PHP 8.4 ou 8.5 ne peut donc pas être utilisée dans le code distribué. PHP 8.5 ne
devient une cible de production qu'après validation explicite du smoke test sur
une image Joomla 6 épinglée et supportée.

## Règles de CI

- La syntaxe et PHPUnit sont exécutés sous PHP 8.3, 8.4 et 8.5.
- L'extension `intl`, requise par CBStats, est déclarée explicitement pour la
  matrice PHPUnit.
- La couverture, PHPStan, PHPCS, Composer, le packaging et le smoke test Joomla
  utilisent PHP 8.4.
- Le smoke test par défaut utilise `joomla:6.1.2-php8.4-apache` et MySQL 8.4.
- La baseline PHPStan ne doit pas être élargie pour masquer une incompatibilité
  de version.

## Classification des constats

Chaque revue de compatibilité distingue :

- **A — ContentBuilder NG** : défaut dans le code ou les tests du projet ;
- **B — Joomla** : comportement ou limitation du CMS ;
- **C — dépendance tierce** : Composer, image Docker ou extension PHP.

Une limitation B ou C doit être documentée et ne doit pas entraîner de shim, de
fallback ou de contournement permanent dans le composant.

## Critères de promotion

Une release peut déclarer PHP 8.4 prêt pour la production uniquement si toutes
les validations PHP 8.3/8.4 sont vertes et si le smoke test Joomla 6/PHP 8.4
réussit. Le statut PHP 8.5 doit rester « expérimental » tant que la même preuve
d'intégration Joomla n'est pas disponible, même si la syntaxe et PHPUnit sont
verts.
