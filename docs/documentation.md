# Rétractation 2026 — Documentation du module

## Vue d’ensemble

**Rétractation 2026** est un module PrestaShop 8+ qui automatise la gestion du droit de rétractation des consommateurs (14 jours calendaires), conformément à la législation française (Ordonnance n° 2026-2 et articles L.221-18 à L.221-28 du Code de la consommation).

### Fonctionnalités principales

- Calcul automatique de l’éligibilité avec un délai de rétractation de 14 jours calendaires.
- Cascade de dates : date de livraison → date d’expédition + buffer → date de commande + buffer.
- Prise en compte de la dernière livraison pour les commandes en plusieurs envois.
- Extension du délai au premier jour ouvrable suivant si le dernier jour tombe un samedi, dimanche ou jour férié métropolitain.
- Formulaire de rétractation pré-rempli pour les clients connectés et formulaire de recherche pour les invités (email + référence de commande).
- Page de confirmation après soumission de la demande.
- Historique des demandes dans le compte client (**Mon compte > Mes rétractations**).
- Tableau de bord administrateur sous **Commandes > Rétractations** : liste, filtres, tri, export, détail, acceptation/refus avec motif obligatoire.
- Panneau latéral sur la fiche commande admin indiquant l’état de la demande ou l’éligibilité.
- Notices précontractuelles sur les fiches produit et dans le panier, avec lien vers le formulaire de rétractation.
- Exclusions configurables par produit ou catégorie (article L.221-28).
- Emails de confirmation, d’acceptation, de refus et de notification marchand (FR + EN).
- Support multiboutique.

## Installation

1. Récupérez le fichier `retractation2026.zip` :
   - depuis la page **Releases** du dépôt GitHub, ou
   - en le générant localement avec le script `scripts/build-zip.sh` (si vous avez les outils de dev en local).
2. Dans le Back Office PrestaShop, allez dans **Modules > Module Manager**.
3. Cliquez sur **Télécharger un module** et sélectionnez le ZIP.
4. Le module s’installe automatiquement et enregistre les hooks nécessaires.
5. Allez dans **Modules > Module Manager**, recherchez **Rétractation 2026** et cliquez sur **Configurer**.

Aucune modification manuelle de la base de données n’est requise. La table `retractation` est créée automatiquement à l’installation et archivée à la désinstallation.

## Configuration

Accédez à la page de configuration via **Modules > Module Manager > Rétractation 2026 > Configurer**.

| Paramètre | Description | Valeur par défaut |
|---|---|---|
| Délai légal de rétractation (jours) | Nombre de jours calendaires du délai légal | 14 |
| Buffer expédition (jours) | Jours ajoutés quand seule la date d’expédition est connue | 7 |
| Buffer commande (jours) | Jours ajoutés quand seule la date de commande est connue | 14 |
| Module actif | Active ou désactive le module | Oui |
| Envoyer un email de confirmation | Envoie un email de confirmation au client | Oui |
| Notifier le marchand par email | Envoie un email de notification à l’adresse de la boutique | Oui |
| Afficher la notice sur les fiches produit | Affiche la notice précontractuelle sur les fiches produit | Oui |
| Afficher la notice dans le panier | Affiche la notice précontractuelle dans le panier | Oui |
| Produits exclus du droit de rétractation | IDs de produits séparés par des virgules (L.221-28) | vide |
| Catégories exclues du droit de rétractation | IDs de catégories séparés par des virgules (L.221-28) | vide |

### Clés de configuration

- `RETRACTATION_DELAY_DAYS`
- `RETRACTATION_BUFFER_SHIPPED`
- `RETRACTATION_BUFFER_ORDER`
- `RETRACTATION_ENABLED`
- `RETRACTATION_EMAIL_ENABLED`
- `RETRACTATION_ADMIN_EMAIL_ENABLED`
- `RETRACTATION_SHOW_PRODUCT_NOTICE`
- `RETRACTATION_SHOW_CART_NOTICE`
- `RETRACTATION_PRODUCT_NOTICE_TEXT`
- `RETRACTATION_CART_NOTICE_TEXT`
- `RETRACTATION_EXCLUDED_PRODUCTS`
- `RETRACTATION_EXCLUDED_CATEGORIES`

## Utilisation côté client

### Faire une demande de rétractation

1. Le client accède à la fiche de sa commande.
2. Si la commande est éligible, un bouton de rétractation s’affiche (via le hook `displayOrderDetail`).
3. En cliquant sur le bouton, il accède au formulaire de rétractation.
   - Client connecté : le formulaire est pré-rempli.
   - Invité : il doit renseigner son email et la référence de commande.
4. Après soumission, une page de confirmation affiche la date et l’heure de la demande.
5. Si l’option est activée, le client reçoit un email de confirmation.

### Historique des demandes

Le client peut consulter ses demandes de rétractation dans son compte (**Mon compte > Mes rétractations**), via le hook `displayCustomerAccount`.

### Notices précontractuelles

Le module affiche des informations sur le droit de rétractation :
- sur les fiches produit (hook `displayProductAdditionalInfo`) ;
- dans le panier (hook `displayShoppingCartFooter`).

Ces notices sont personnalisables dans la configuration. Si un produit est exclu du droit de rétractation (virtuel ou configuré comme exclu), la notice adapte son message.

## Utilisation côté marchand

### Tableau de bord administrateur

Un onglet **Rétractations** est ajouté sous le menu **Commandes**. Il permet de :

- lister toutes les demandes de rétractation ;
- filtrer et trier par date, commande, client, statut ;
- exporter la liste ;
- consulter le détail d’une demande ;
- accepter ou refuser une demande (le refus exige un motif) ;
- envoyer un email au client en cas d’acceptation ou de refus.

### Panneau latéral sur la fiche commande

Lors de la consultation d’une commande dans le Back Office, le panneau latéral affiche :
- la demande de rétractation en cours si elle existe ;
- l’éligibilité de la commande si aucune demande n’a été faite ;
- un lien vers le tableau de bord des rétractations.

## Multiboutique

Le module prend en charge le multiboutique PrestaShop :

- les valeurs de configuration sont indépendantes par boutique ;
- les données de rétractation sont filtrées par `id_shop` ;
- le tableau de bord admin n’affiche que les demandes de la boutique en cours ;
- chaque boutique peut avoir ses propres délais, buffers et exclusions.

## Compatibilité

| Prérequis | Version |
|---|---|
| PrestaShop | 8.0.0 à 9.99.99 |
| PHP | 7.4+ (comme requis par PrestaShop 8) |

### Hooks enregistrés

| Hook | Rôle |
|---|---|
| `displayOrderDetail` | Affiche le bouton de rétractation sur la fiche commande |
| `displayCustomerAccount` | Ajoute le lien vers l’historique des rétractations |
| `displayAdminOrderSide` | Affiche le panneau rétractation sur la fiche commande admin |
| `displayProductAdditionalInfo` | Notice précontractuelle sur la fiche produit |
| `displayShoppingCartFooter` | Notice précontractuelle dans le panier |

## Traductions

Les textes du module sont en anglais dans les templates et les contrôleurs (source), et traduits en français via le catalogue de traductions PrestaShop (XLF + base `ps_translation`).

Pour régénérer le catalogue Symfony après une mise à jour du module :

1. Videz le cache PrestaShop : **Configuration avancée > Performances > Vider le cache**.
2. Régénérez les traductions françaises : **International > Traductions > Ajouter / Mettre à jour une langue** > choisissez **Français (French)**.

Alternativement, vous pouvez supprimer manuellement le dossier `var/cache/prod/translations/` (et `dev/translations/` si vous êtes en mode debug) sur le serveur, puis recharger une page front-office pour forcer la recompilation.

## Contexte juridique

Ce module met en œuvre le droit de rétractation tel que défini par l’**Ordonnance n° 2026-2**, qui renforce les articles **L.221-18 à L.221-28 du Code de la consommation**.

Points clés :

- Le consommateur dispose d’un délai de **14 jours calendaires** à compter de la réception des biens pour exercer son droit de rétractation.
- Le professionnel doit informer le consommateur de ce droit avant l’achat (information précontractuelle).
- Le consommateur peut exercer ce droit sans justification et sans pénalité.
- Le module calcule la date butoir en privilégiant la date de livraison, puis la date d’expédition avec buffer, puis la date de commande avec buffer.
- Si le dernier jour du délai tombe un samedi, dimanche ou jour férié, il est prolongé jusqu’au premier jour ouvrable suivant.

Ce module assiste les marchands dans la mise en conformité mais ne constitue **pas un avis juridique**. Les marchands doivent consulter un professionnel du droit pour valider leurs CGV et leur processus.

## Support et contributions

- Dépôt GitHub : https://github.com/gatch14/retractation
- Pour signaler un bug ou proposer une amélioration, utilisez les **Issues** du dépôt.

## Licence

Ce module est distribué sous licence **Academic Free License version 3.0 (AFL-3.0)**.
