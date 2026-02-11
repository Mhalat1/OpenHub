<?php

namespace App\Documentation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\BusinessLimits;
/**
 * Documentation des limites de l'API
 * 
 * Cette classe sert de référence pour les développeurs frontend
 * et les testeurs pour comprendre les limites métier de l'application.
 */
class ApiLimitsDocumentation
{
    public static function getLimitsAsArray(): array
    {
        return [
            'pagination' => [
                'min_page' => BusinessLimits::PAGINATION_MIN_PAGE,
                'max_page' => BusinessLimits::PAGINATION_MAX_PAGE,
                'min_limit' => BusinessLimits::PAGINATION_MIN_LIMIT,
                'max_limit' => BusinessLimits::PAGINATION_MAX_LIMIT,
                'default_limit' => BusinessLimits::PAGINATION_DEFAULT_LIMIT,
                'max_offset' => BusinessLimits::PAGINATION_MAX_OFFSET,
                'description' => 'Limites de pagination pour GET /api/get/messages'
            ],
            'content_size' => [
                'http_payload_max' => BusinessLimits::HTTP_PAYLOAD_MAX_SIZE . ' bytes',
                'conversation_title_max' => BusinessLimits::CONVERSATION_TITLE_MAX_LENGTH . ' chars',
                'conversation_description_max' => BusinessLimits::CONVERSATION_DESCRIPTION_MAX_LENGTH . ' chars',
                'message_content_max' => BusinessLimits::MESSAGE_CONTENT_MAX_LENGTH . ' chars',
                'user_name_max' => BusinessLimits::USER_NAME_MAX_LENGTH . ' chars',
                'user_name_min' => BusinessLimits::USER_NAME_MIN_LENGTH . ' chars',
                'description' => 'Limites de taille pour tous les champs textuels'
            ],
            'participants' => [
                'conversation_max_participants' => BusinessLimits::CONVERSATION_MAX_PARTICIPANTS,
                'conversation_min_participants' => BusinessLimits::CONVERSATION_MIN_PARTICIPANTS,
                'description' => 'Limites de participants pour POST /api/create/conversation'
            ],
            'rate_limits' => [
                'messages_per_day_per_conversation' => BusinessLimits::MESSAGE_RATE_LIMIT_PER_DAY,
                'conversations_create_per_day' => BusinessLimits::CONVERSATION_CREATE_RATE_LIMIT_PER_DAY,
                'conversations_delete_per_day' => BusinessLimits::CONVERSATION_DELETE_RATE_LIMIT_PER_DAY,
                'window_hours' => BusinessLimits::RATE_LIMIT_WINDOW_HOURS,
                'description' => 'Limites de taux pour prévenir le spam'
            ],
            'deletion' => [
                'max_messages_for_direct_delete' => BusinessLimits::CONVERSATION_MAX_MESSAGES_FOR_DELETE,
                'description' => 'Conversations avec plus de messages nécessitent un contact support'
            ],
            'availability' => [
                'max_range_days' => BusinessLimits::AVAILABILITY_MAX_RANGE_DAYS,
                'max_range_years' => BusinessLimits::AVAILABILITY_MAX_RANGE_DAYS / 365,
                'description' => 'Limites pour les dates de disponibilité utilisateur'
            ]
        ];
    }

    /**
     * Endpoint pour exposer les limites aux clients
     */
    #[Route('/api/limits', name: 'api_limits', methods: ['GET'])]
    public function getLimits(): JsonResponse
    {
        return new JsonResponse(self::getLimitsAsArray());
    }
}