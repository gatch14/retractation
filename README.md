# Rétractation 2026 — Module PrestaShop

Module PrestaShop 8+ pour gérer le droit de rétractation des consommateurs conformément à la législation française (Ordonnance n° 2026-2, articles L.221-18 à L.221-28 du Code de la consommation).

## Fonctionnalités

- Calcul automatique de l’éligibilité sur 14 jours calendaires.
- Cascade de dates : date de livraison → date d’expédition + buffer → date de commande + buffer.
- Formulaire de rétractation pré-rempli pour les clients connectés, accessible aux invités par email + référence de commande.
- Historique des demandes dans le compte client.
- Tableau de bord administrateur sous **Commandes > Rétractations**.
- Panneau latéral sur la fiche commande admin.
- Notices précontractuelles sur fiche produit et panier (désactivables).
- Emails de confirmation, d’acceptation, de refus et de notification marchand (FR + EN).
- Support multiboutique.

## Structure du dépôt

- `retractation2026/` — le module PrestaShop proprement dit.
- `docs/` — documentation utilisateur et guide juridique CGV.
- `scripts/` — scripts de packaging et de vérification (build-zip, verify-s*).

Les fichiers `package.json`, `node_modules/`, `scripts/generate-*.js`, `scripts/logo-output/` et `retractation2026.zip` ne font **pas partie du module** : ce sont des outils/outils graphiques de développement.

## Installation

1. Téléchargez le ZIP `retractation2026.zip` (il ne contient que le dossier `retractation2026/`).
2. Dans le Back Office PrestaShop : **Modules > Module Manager > Télécharger un module**.
3. Configurez les délais et buffers sous **Configurer**.

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

## Hooks enregistrés

- `displayOrderDetail`
- `displayCustomerAccount`
- `displayAdminOrderSide`
- `displayProductAdditionalInfo`
- `displayShoppingCartFooter`

## Notes de version 1.0.1

- Ajout du champ `reject_reason` dans l’ObjectModel.
- Suppression de la contrainte d’unicité trop stricte qui bloquait une nouvelle demande après annulation.
- Désinstallation sécurisée : les données sont archivées avec un horodatage au lieu d’être écrasées.
- Suppression de l’insertion automatique dans le footer (couplage fragile avec `ps_linklist`).
- Suppression du hook `displayHeader` inutilisé.
- Correction de la documentation (hooks enregistrés).
- Traductions complètes du panneau latéral admin.
- Ajout de l’email de notification au marchand.

## Mise en production / GitHub

- Ne poussez que `docs/` et `retractation2026/` sur GitHub (les outils de dev sont exclus via `.gitignore`).
- Le ZIP de release doit contenir uniquement le dossier `retractation2026/`.
- Testez sur un PrestaShop 8+ avec PHP 7.4+ avant la production.
- Pensez à mettre à jour vos CGV : voir `docs/guide-cgv.md`.

## Avertissement juridique

Ce module aide à respecter le cadre légal du droit de rétractation mais ne constitue **pas un avis juridique**. Faites valider vos CGV et votre processus par un professionnel du droit.

## Licence

Academic Free License version 3.0 (AFL-3.0).
