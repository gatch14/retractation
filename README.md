# Rétractation 2026 — Module PrestaShop

Module PrestaShop 8+ pour gérer le droit de rétractation des consommateurs conformément à la législation française (Ordonnance n° 2026-2, articles L.221-18 à L.221-28 du Code de la consommation).

## Fonctionnalités

- Calcul automatique du délai de rétractation (14 jours calendaires par défaut).
- Cascade de dates : date de livraison → date d’expédition + buffer → date de commande + buffer.
- Prise en compte de la dernière livraison pour les commandes en plusieurs envois.
- Extension au premier jour ouvrable suivant si le dernier jour du délai est un samedi, dimanche ou jour férié métropolitain.
- Formulaire de rétractation en ligne pré-rempli pour les clients connectés, accessible aux invités par email + référence de commande.
- Historique des demandes dans le compte client.
- Tableau de bord administrateur sous **Commandes > Rétractations**.
- Panneau latéral sur la fiche commande admin.
- Notices précontractuelles sur fiche produit et panier (désactivables), avec lien vers le formulaire.
- Exclusions configurables par produits ou catégories (article L.221-28).
- Emails de confirmation, d’acceptation, de refus et de notification marchand (FR + EN).
- Support multiboutique.

## Structure du dépôt

- `retractation2026/` — le module PrestaShop proprement dit.
- `docs/` — documentation utilisateur et guide juridique CGV.

Les outils de développement (scripts de build/verif, logo, `package.json`, `node_modules/`, `.mcp.json`, `.planning/`) ne sont **pas versionnés** : ils restent en local.

## Installation

1. Téléchargez le ZIP `retractation2026.zip` (il ne contient que le dossier `retractation2026/`).
2. Dans le Back Office PrestaShop : **Modules > Module Manager > Télécharger un module**.
3. Configurez les délais, buffers et exclusions sous **Configurer**.

Aucune modification manuelle de la base de données n’est requise.

## Configuration

| Clé | Description | Défaut |
|---|---|---|
| `RETRACTATION_DELAY_DAYS` | Délai légal en jours | 14 |
| `RETRACTATION_BUFFER_SHIPPED` | Buffer si seule la date d’expédition est connue | 7 |
| `RETRACTATION_BUFFER_ORDER` | Buffer si seule la date de commande est connue | 14 |
| `RETRACTATION_ENABLED` | Active/désactive le module | 1 |
| `RETRACTATION_EMAIL_ENABLED` | Email de confirmation client | 1 |
| `RETRACTATION_ADMIN_EMAIL_ENABLED` | Email de notification au marchand | 1 |
| `RETRACTATION_SHOW_PRODUCT_NOTICE` | Notice sur fiche produit | 1 |
| `RETRACTATION_SHOW_CART_NOTICE` | Notice dans le panier | 1 |
| `RETRACTATION_EXCLUDED_PRODUCTS` | IDs de produits exclus du droit de rétractation | vide |
| `RETRACTATION_EXCLUDED_CATEGORIES` | IDs de catégories exclues du droit de rétractation | vide |

## Hooks enregistrés

- `displayOrderDetail`
- `displayCustomerAccount`
- `displayAdminOrderSide`
- `displayProductAdditionalInfo`
- `displayShoppingCartFooter`

## Notes de version 1.1.0

- Extension du délai au jour ouvrable suivant en cas de samedi, dimanche ou jour férié.
- Utilisation de la dernière date de livraison pour les commandes en plusieurs envois.
- Ajout d’exclusions configurables par produit ou catégorie (L.221-28).
- Enrichissement des notices précontractuelles (délai, point de départ, lien vers le formulaire).

## Mise en production / GitHub

- Ne poussez que `docs/` et `retractation2026/` sur GitHub (les outils de dev restent en local).
- Le ZIP de release doit contenir uniquement le dossier `retractation2026/`.
- Testez sur un PrestaShop 8+ avec PHP 7.4+ avant la production.
- Pensez à mettre à jour vos CGV : voir `docs/guide-cgv.md`.

## Avertissement juridique

Ce module aide à respecter le cadre légal du droit de rétractation mais ne constitue **pas un avis juridique**. Faites valider vos CGV, mentions légales et processus par un professionnel du droit.

## Licence

Academic Free License version 3.0 (AFL-3.0).
