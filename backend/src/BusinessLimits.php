<?php

namespace App;
/**
 * Configuration centralisée des limites métier
 * 
 * IMPORTANT : Toute modification de ces valeurs doit être documentée
 * et testée pour impact sur la performance et la sécurité.
 */
class BusinessLimits
{
    // ===== LIMITES DE PAGINATION =====
    
    /**
     * Nombre minimum de résultats par page
     * Justification : Éviter les requêtes trop fréquentes
     */
    public const PAGINATION_MIN_LIMIT = 10;
    
    /**
     * Nombre maximum de résultats par page
     * Justification : Limiter la charge mémoire PHP et la taille des réponses JSON
     * Impact : ~100 messages × 500 octets/message = ~50 KB par réponse
     */
    public const PAGINATION_MAX_LIMIT = 100;
    
    /**
     * Nombre de résultats par défaut
     * Justification : Équilibre entre UX et performance
     */
    public const PAGINATION_DEFAULT_LIMIT = 20;
    
    /**
     * Page minimum
     */
    public const PAGINATION_MIN_PAGE = 1;
    
    /**
     * Page maximum
     * Justification : Éviter les requêtes avec offset astronomiques
     * Limite effective : 1000 × 100 = 100,000 messages accessibles
     */
    public const PAGINATION_MAX_PAGE = 1000;
    
    /**
     * Offset maximum absolu (calculé)
     * Justification : Protection contre les requêtes SQL lentes
     */
    public const PAGINATION_MAX_OFFSET = self::PAGINATION_MAX_PAGE * self::PAGINATION_MAX_LIMIT;
    
    // ===== LIMITES DE CONTENU =====
    
    /**
     * Taille maximale du payload HTTP (octets)
     * Justification : Protection contre les attaques DoS
     */
    public const HTTP_PAYLOAD_MAX_SIZE = 10000;
    
    /**
     * Taille maximale du titre de conversation (caractères UTF-8)
     * Justification : Affichage dans l'UI sans troncature
     */
    public const CONVERSATION_TITLE_MAX_LENGTH = 255;
    
    /**
     * Taille maximale de la description (caractères UTF-8)
     * Justification : Limite base de données (TEXT) et affichage UI
     */
    public const CONVERSATION_DESCRIPTION_MAX_LENGTH = 1000;
    
    /**
     * Taille maximale du contenu d'un message (caractères UTF-8)
     * Justification : Messages courts type chat/SMS
     */
    public const MESSAGE_CONTENT_MAX_LENGTH = 250;
    
    /**
     * Taille maximale d'un nom (prénom ou nom de famille)
     * Justification : 99.9% des noms réels < 100 caractères
     */
    public const USER_NAME_MAX_LENGTH = 100;
    
    /**
     * Taille minimale d'un nom
     * Justification : Éviter les noms vides ou trop courts
     */
    public const USER_NAME_MIN_LENGTH = 2;
    
    /**
     * Taille maximale d'un email
     * Justification : RFC 5321 (320 caractères) + marge de sécurité
     */
    public const USER_EMAIL_MAX_LENGTH = 255;
    
    // ===== LIMITES DE PARTICIPANTS =====
    
    /**
     * Nombre maximum de participants par conversation
     * Justification : 
     * - Performance : 50 utilisateurs × 1 KB = ~50 KB chargés en mémoire
     * - UX : Au-delà de 50 participants, l'interface devient inutilisable
     */
    public const CONVERSATION_MAX_PARTICIPANTS = 50;
    
    /**
     * Nombre minimum de participants (incluant le créateur)
     * Justification : Une conversation nécessite au moins 2 personnes
     */
    public const CONVERSATION_MIN_PARTICIPANTS = 2;
    
    // ===== LIMITES DE RATE LIMITING =====
    
    /**
     * Nombre maximum de messages par jour et par conversation
     * Justification : Protection contre le spam
     * Exemple : 100 messages / 24h = ~4 messages/heure = usage normal
     */
    public const MESSAGE_RATE_LIMIT_PER_DAY = 100;
    
    /**
     * Nombre maximum de conversations créées par utilisateur par jour
     * Justification : Protection contre le spam de conversations
     */
    public const CONVERSATION_CREATE_RATE_LIMIT_PER_DAY = 20;
    
    /**
     * Nombre maximum de suppressions de conversations par jour
     * Justification : Protection contre les suppressions massives accidentelles
     */
    public const CONVERSATION_DELETE_RATE_LIMIT_PER_DAY = 50;
    
    /**
     * Fenêtre de temps pour le rate limiting (en heures)
     */
    public const RATE_LIMIT_WINDOW_HOURS = 24;
    
    // ===== LIMITES DE SUPPRESSION =====
    
    /**
     * Nombre maximum de messages dans une conversation pour suppression directe
     * Justification : Au-delà de 10,000 messages :
     * - Temps de suppression > 30 secondes
     * - Risque de timeout PHP
     * - Nécessite une suppression asynchrone
     */
    public const CONVERSATION_MAX_MESSAGES_FOR_DELETE = 10000;
    
    // ===== LIMITES DE DISPONIBILITÉ =====
    
    /**
     * Écart maximum entre dates de disponibilité (en jours)
     * Justification : 2 ans = période raisonnable pour planifier
     */
    public const AVAILABILITY_MAX_RANGE_DAYS = 730;
    
    // ===== LIMITES DE SÉCURITÉ =====
    
    /**
     * Limite de backtracking PCRE pour les regex
     * Justification : Protection contre les attaques ReDoS
     */
    public const PCRE_BACKTRACK_LIMIT = 100000;
    
    /**
     * Temps d'exécution maximum pour une requête (secondes)
     * Justification : Protection contre les timeouts
     */
    public const MAX_EXECUTION_TIME = 30;
    
    /**
     * Profondeur maximale de décodage JSON
     * Justification : Protection contre les attaques par JSON profond
     */
    public const JSON_MAX_DEPTH = 10;
    
    /**
     * Nombre maximum d'itérations pour le décodage canonique
     * Justification : Protection contre les boucles infinies
     */
    public const CANONICAL_DECODE_MAX_ITERATIONS = 3;


        // ✅ AJOUTER ces 2 constantes seulement
    public const CONVERSATION_DELETION_REQUIRES_APPROVAL = true; // Activer/désactiver
    public const MIN_APPROVERS_REQUIRED = 2; // Nombre d'approbateurs minimum
    
}