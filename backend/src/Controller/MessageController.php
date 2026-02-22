<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use App\BusinessLimits;
use Psr\Log\LoggerInterface;


class MessageController extends AbstractController
{
    private MessageRepository $messageRepository;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    // ✅ NOUVEAU : Constantes pour cohérence des parsers
    private const ENCODING = 'UTF-8';
    private const HTML_ENTITY_FLAGS = ENT_QUOTES | ENT_HTML5;

    
    public function __construct(
        MessageRepository $messageRepository,
        EntityManagerInterface $em,
        LoggerInterface $logger       
    ) {

        $this->messageRepository = $messageRepository;
        $this->em = $em;
        $this->logger = $logger;
        // ✅ Utiliser les constantes centralisées
        ini_set('pcre.backtrack_limit', (string) BusinessLimits::PCRE_BACKTRACK_LIMIT);
        ini_set('max_execution_time', (string) BusinessLimits::MAX_EXECUTION_TIME);
        mb_internal_encoding(self::ENCODING);
    }


    private function canonicalDecode(string $input): string

    {
        // ✅ Utiliser la constante
        if (strlen($input) > BusinessLimits::HTTP_PAYLOAD_MAX_SIZE) {
            throw new \InvalidArgumentException(sprintf(
                'Input too long (max %d bytes)',
                BusinessLimits::HTTP_PAYLOAD_MAX_SIZE
            ));
        }

        $previous = '';
        $current = $input;
        $iterations = 0;

        // ✅ Utiliser la constante
        while ($previous !== $current && $iterations < BusinessLimits::CANONICAL_DECODE_MAX_ITERATIONS) {

            $previous = $current;
            
            // Décodage HTML entities seulement si pattern détecté avec regex
            // ✅: Ajout de limite {10}
            if (preg_match('/&[a-zA-Z0-9#]{1,10};/', $current)) {
                $decoded = html_entity_decode($current, self::HTML_ENTITY_FLAGS, self::ENCODING);
                if ($decoded !== $current) {
                    $current = $decoded;
                }
            }
            
            // Décodage URL seulement si pattern détecté avec regex
            // ✅  (limite {2} présente)
            if (preg_match('/%[0-9A-Fa-f]{2}/', $current)) {
                $decoded = urldecode($current);
                if ($decoded !== $current) {
                    $current = $decoded;
                }
            }
            
            $iterations++;
        }
        
        return $current;
    }

    private function validateString(string $value, int $maxLength): bool
    {
        // verifie la longueur ✅ AJOUT : Protection longueur absolue
        if (empty($value) || mb_strlen($value) > $maxLength || mb_strlen($value) > 10000) {
            return false;
        }
        
        // Allow: letters, numbers, spaces, newlines, and specific punctuation only
        // Rejette: %, $, #, &, ^, etc.
            // ✅ MODIFIÉ : Ajout du modificateur 'D' (aucun backtracking sur fin de ligne)
        if (!preg_match('/^[\p{L}\p{N}\s\.,;:!?\'\"-()\/\n]+$/uD', $value)) {
            return false;
        }

                // ⚠️ BLOQUE les caractères dangereux pour CSV : = + - @ \t \0
        $firstChar = mb_substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\0"], true)) {
            return false; // ← Rejette si commence par un caractère dangereux
        }
        
        //✅ BLOQUE les patterns dangereux (XSS, SQL injection)
        $dangerousPatterns = [
            '/<script/i',
            '/<iframe/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/\bselect\b.*\bfrom\b/i',
            '/\bunion\b.*\bselect\b/i',
            '/\bdrop\b.*\btable\b/i',
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }
        
        return true;
    }

private function validateName(string $name, int $maxLength = 20): bool
{
    // Vérifier la longueur
    if (empty($name) || mb_strlen($name) > $maxLength || mb_strlen($name) < 2) {
        return false;
    }
    
    // ✅ NOUVEAU : Pas d'espace, tiret ou apostrophe au début/fin
    if (preg_match('/^[\s\-\']|[\s\-\']$/', $name)) {
        return false;
    }
    
    // ✅ RÈGLE MÉTIER : Noms doivent contenir uniquement : - Lettres - Espaces  - Tirets - Apostrophes 
    if (!preg_match('/^[\p{L}\s\'\-]+$/uD', $name)) {
        return false;
    }
    
    // ✅ RÈGLE MÉTIER : Pas de chiffres dans les noms
    if (preg_match('/\d/', $name)) {
        return false;
    }
    
    // ✅ RÈGLE MÉTIER : Pas de caractères spéciaux dangereux
    $dangerousChars = ['<', '>', '&', '"', '\\', '/', '@', '#', '$', '%', '^', '*', '(', ')', '=', '+', '[', ']', '{', '}'];
    foreach ($dangerousChars as $char) {
        if (str_contains($name, $char)) {
            return false;
        }
    }
    
    // ✅ RÈGLE MÉTIER : Pas plus de 2 espaces/tirets/apostrophes consécutifs
    if (preg_match('/[\s\'\-]{3,}/', $name)) {
        return false;
    }
    
    return true;
}


private function sanitizeData(array $data): array
{
    $sanitized = [];
   
    // ✅ Nettoie avec HTMLPurifier (pas de HTML autorisé par défaut)
    if (isset($data['title'])) {
        // Titre : AUCUN HTML autorisé
        $sanitized['title'] = $this->sanitizeHtml($data['title'], false);
    }

    if (isset($data['description'])) {
        // Description : Formatage basique autorisé (si éditeur WYSIWYG)
        $sanitized['description'] = $this->sanitizeHtml($data['description'], true);
    }
    
    if (isset($data['content'])) {
        // Contenu message : Formatage basique autorisé (si éditeur WYSIWYG)
        $sanitized['content'] = $this->sanitizeHtml($data['content'], true);
    }
    
    if (isset($data['conv_users']) && is_array($data['conv_users'])) {
        $sanitized['conv_users'] = array_unique(
            array_filter(
                array_map('intval', $data['conv_users']),
                fn($id) => $id > 0
            )
        );
    }
    
    if (isset($data['conversation_id'])) {
        $sanitized['conversation_id'] = (int) $data['conversation_id'];
    }

    return $sanitized;
}

    private function validateMessageContent(string $content): bool
    {
        // ✅ Utiliser la constante
        return $this->validateString($content, BusinessLimits::MESSAGE_CONTENT_MAX_LENGTH);
    }

private function sanitizeHtml(?string $html, bool $allowFormatting = false): string
{
    if ($html === null || $html === '') {
        return '';
    }
    
    $config = \HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    
    if ($allowFormatting) {
        // Version STRICTE : Seulement formatage texte
        $config->set('HTML.Allowed', 'strong,em,u,br,p');
        // Aucun lien, aucune image, aucune liste
    } else {
        $config->set('HTML.Allowed', '');
    }
    
    // Protection maximale
    $config->set('HTML.SafeIframe', false);
    $config->set('HTML.SafeObject', false);
    $config->set('HTML.SafeEmbed', false);
    $config->set('URI.DisableExternal', true); // Bloque tous les liens externes
    
    $purifier = new \HTMLPurifier($config);
    return $purifier->purify($html);
}


private function sanitizeForJson($value)
{
    if ($value === null) {
        return null;
    }
    
    if (is_array($value)) {
        return array_map([$this, 'sanitizeForJson'], $value);
    }
    
    if (is_string($value)) {
        // Supprimer les caractères de contrôle (0x00-0x1F sauf \n, \r, \t)
        // et le caractère DEL (0x7F)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        return $value;
    }
    
    return $value;
}






private function validateAvailabilityDates(?\DateTimeImmutable $minDate, ?\DateTimeImmutable $maxDate): array
{
    // Si aucune date n'est définie, c'est non valide
    if (!$minDate && !$maxDate) {
        return [
            'valid' => false, 
            'error' => 'maxDate date must be after minDate date'
        ];
    }

    // Si seulement une date est définie, c'est non valide
    if (!$minDate || !$maxDate) {
        return [
            'valid' => false, 
            'error' => 'Both minDate and maxDate must be defined'
        ];
    }

    // ✅ RÈGLE 1 : Date de fin APRÈS date de début
    if ($maxDate <= $minDate) {
        return [
            'valid' => false, 
            'error' => 'maxDate date must be after minDate date'
        ];
    }

    // ✅ RÈGLE 2 : Écart maximum de 2 ans
    $interval = $minDate->diff($maxDate);
    $totalDays = $interval->days;
    
    if ($totalDays > 730) { // 2 ans = 730 jours
        return [
            'valid' => false,
            'error' => 'Date range cannot exceed 2 years'
        ];
    }


    $today = new \DateTimeImmutable();
    if ($maxDate <= $today) {  // ← Changement ici : si la date est aujourd'hui ou dans le passé
        return [
            'valid' => false,
            'error' => 'End Date must be in the future'  // ← Le message reste correct
        ];
    }


    return ['valid' => true, 'error' => null];
}



private function validateConversationDeleteRateLimit(User $user, EntityManagerInterface $em): array
{
    try {
        $windowStart = new \DateTimeImmutable(
            sprintf('-%d hours', BusinessLimits::RATE_LIMIT_WINDOW_HOURS)
        );
        
        // Compter les suppressions récentes (nécessite une table d'audit)
        // Pour l'instant, on accepte toutes les suppressions
        // TODO : Implémenter un système d'audit des suppressions
        
        return ['valid' => true, 'error' => null];
        
    } catch (\Exception $e) {
        $this->logger->warning('Rate limit check failed', [
            'user_id' => $user->getId(),
            'error'   => $e->getMessage(),
        ]);
        return ['valid' => true, 'error' => null];
    }
}

private function validateConversationParticipants(User $creator, array $userIds, EntityManagerInterface $em): array
{
    // ✅ RÈGLE 1 : Maximum 50 participants
    if (count($userIds) > 50) {
        return [
            'valid' => false,
            'error' => 'Maximum 50 participants allowed per conversation',
            'validUsers' => []
        ];
    }

    // ✅ RÈGLE 2 : Validation et collecte des IDs valides
    $validUserIds = [];
    foreach ($userIds as $userId) {
        if (!is_numeric($userId)) {
            continue;
        }
        
        $userId = (int) $userId;
        
        if ($userId <= 0) {
            continue;
        }
        
        // Ne pas ajouter le créateur deux fois
        if ($userId === $creator->getId()) {
            continue;
        }
        
        $validUserIds[] = $userId;
    }

    // ✅ RÈGLE 3 : Minimum 1 participant (en plus du créateur)
    if (empty($validUserIds)) {
        return [
            'valid' => false,
            'error' => 'A conversation must have at least 2 participants (creator + 1 other user)',
            'validUsers' => []
        ];
    }

    // ✅ RÈGLE 4 : Vérifier que tous les utilisateurs existent en base
    $users = $em->getRepository(User::class)->findBy(['id' => $validUserIds]);
    
    if (count($users) !== count($validUserIds)) {
        // Certains IDs n'existent pas
        $foundIds = array_map(fn($u) => $u->getId(), $users);
        $missingIds = array_diff($validUserIds, $foundIds);
        
        return [
            'valid' => false,
            'error' => 'Invalid user IDs: ' . implode(', ', $missingIds),
            'validUsers' => []
        ];
    }

    return [
        'valid' => true,
        'error' => null,
        'validUsers' => $users
    ];
}
private function validateEmailandUniqueness(string $email, int $currentUserId, EntityManagerInterface $em ): array
{
    // ✅ ÉTAPE 1 : Validation du format de l'email
    if (strlen($email) > 255) {
        return [
            'valid' => false,
            'error' => 'Email is too long (maximum 255 characters)'
        ];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'error' => 'Invalid email format'
        ];
    }

    if  (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        return [
            'valid' => false,
            'error' => 'Invalid email format (use format: nom@domaine.xxx)'
        ];
    }

    
    // ✅ ÉTAPE 2 : Vérification de l'unicité en base de données
    $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
    
    if ($existingUser && $existingUser->getId() !== $currentUserId) {
        return [
            'valid' => false,
            'error' => 'Email already in use by another account'
        ];
    }

    // ✅ ÉTAPE 3 : Email valide et disponible
    return ['valid' => true, 'error' => null];
}

    private function validateMessageRateLimit(User $user, Conversation $conversation, EntityManagerInterface $em): array
    {
        try {
            // ✅ CORRECTION : Utiliser DateTimeImmutable pour éviter les mutations accidentelles
            $yesterday = new \DateTimeImmutable('-24 hours');
            
            // ✅ CORRECTION : Typage explicite du résultat
            $recentMessagesCount = (int) $em->getRepository(Message::class)
                ->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.author = :user')
                ->andWhere('m.conversation = :conversation')
                ->andWhere('m.createdAt >= :yesterday')
                ->setParameter('user', $user)
                ->setParameter('conversation', $conversation)
                ->setParameter('yesterday', $yesterday)
                ->getQuery()
                ->getSingleScalarResult();
            
            // ✅ AMÉLIORATION : Constante pour la limite (facilite la maintenance)
            $maxMessagesPerDay = 100;
            
            if ($recentMessagesCount >= $maxMessagesPerDay) {
                return [
                    'valid' => false,
                    'error' => "Message rate limit exceeded. Maximum {$maxMessagesPerDay} messages per day per conversation."
                ];
            }
            
            return ['valid' => true, 'error' => null];
            
        } catch (\Exception $e) {
            $this->logger->warning('Rate limit check failed', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);            
            // En cas d'erreur, on autorise par défaut (fail-open)
            // Alternative : return ['valid' => false, 'error' => 'Rate limit check failed'];
            return ['valid' => true, 'error' => null];
        }
    }

    private function validateUserState(User $user): array
{
    // Forcer l'affichage dans les logs Render
    error_log('=== VALIDATE USER STATE START ===');
    error_log('User ID: ' . $user->getId());
    error_log('Email: ' . $user->getEmail());
    error_log('FirstName: "' . $user->getFirstName() . '"');
    error_log('LastName: "' . $user->getLastName() . '"');
    error_log('FirstName length: ' . mb_strlen($user->getFirstName()));
    error_log('LastName length: ' . mb_strlen($user->getLastName()));

    $this->logger->error('NEW CODE IS RUNNING - validateUserState test');

    // Vérifier les noms avec logs forcés
    $firstNameValid = $this->validateName($user->getFirstName());
    $lastNameValid = $this->validateName($user->getLastName());
    
    error_log('FirstName valid: ' . ($firstNameValid ? 'true' : 'false'));
    error_log('LastName valid: ' . ($lastNameValid ? 'true' : 'false'));

    if (!$firstNameValid || !$lastNameValid) {
        $reason = [];
        if (!$firstNameValid) {
            // Tester chaque condition
            $firstName = $user->getFirstName();
            
            if (empty($firstName) || mb_strlen($firstName) > 20 || mb_strlen($firstName) < 2) {
                $reason[] = 'length';
                error_log('FAIL: FirstName length=' . mb_strlen($firstName) . ' (min=2, max=20)');
            }
            if (preg_match('/^[\s\-\']|[\s\-\']$/', $firstName)) {
                $reason[] = 'start_end_invalid';
                error_log('FAIL: FirstName starts/ends with invalid char');
            }
            if (!preg_match('/^[\p{L}\s\'\-]+$/uD', $firstName)) {
                $reason[] = 'invalid_chars';
                error_log('FAIL: FirstName contains invalid characters');
            }
            if (preg_match('/\d/', $firstName)) {
                $reason[] = 'contains_numbers';
                error_log('FAIL: FirstName contains numbers');
            }
            
            // Afficher les caractères dangereux
            $dangerousChars = ['<', '>', '&', '"', '\\', '/', '@', '#', '$', '%', '^', '*', '(', ')', '=', '+', '[', ']', '{', '}'];
            foreach ($dangerousChars as $char) {
                if (str_contains($firstName, $char)) {
                    $reason[] = 'dangerous_char_' . $char;
                    error_log("FAIL: FirstName contains dangerous char: $char");
                }
            }
            
            if (preg_match('/[\s\'\-]{3,}/', $firstName)) {
                $reason[] = 'too_many_consecutive';
                error_log('FAIL: FirstName has too many consecutive special chars');
            }
        }
        
        error_log('validateUserState FAILED: ' . implode(', ', $reason));
        
        return ['valid' => false, 'error' => 'Invalid user name'];
    }
    
    // Vérifier l'email
    $emailCheck = $this->validateEmailandUniqueness($user->getEmail(), $user->getId(), $this->em);
    error_log('Email valid: ' . ($emailCheck['valid'] ? 'true' : 'false'));
    
    if (!$emailCheck['valid']) {
        error_log('Email error: ' . $emailCheck['error']);
        return ['valid' => false, 'error' => 'Invalid user email'];
    }
    
    // Vérifier les dates si présentes
    if ($user->getAvailabilityStart() && $user->getAvailabilityEnd()) {
        $dateCheck = $this->validateAvailabilityDates(
            $user->getAvailabilityStart(), 
            $user->getAvailabilityEnd()
        );
        error_log('Dates valid: ' . ($dateCheck['valid'] ? 'true' : 'false'));
        
        if (!$dateCheck['valid']) {
            error_log('Date error: ' . $dateCheck['error']);
            return ['valid' => false, 'error' => 'Invalid availability dates'];
        }
    }
    
    error_log('validateUserState PASSED');
    return ['valid' => true, 'error' => null];
}

private function validateConversationCreateRateLimit(User $user, EntityManagerInterface $em): array
{
    try {
        $windowStart = new \DateTimeImmutable(
            sprintf('-%d hours', BusinessLimits::RATE_LIMIT_WINDOW_HOURS)
        );
        
        $recentConversationsCount = (int) $em->getRepository(Conversation::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdBy = :user')
            ->andWhere('c.createdAt >= :windowStart')
            ->setParameter('user', $user)
            ->setParameter('windowStart', $windowStart)
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($recentConversationsCount >= BusinessLimits::CONVERSATION_CREATE_RATE_LIMIT_PER_DAY) {
            return [
                'valid' => false,
                'error' => sprintf(
                    'Conversation creation rate limit exceeded. Maximum %d conversations per %d hours.',
                    BusinessLimits::CONVERSATION_CREATE_RATE_LIMIT_PER_DAY,
                    BusinessLimits::RATE_LIMIT_WINDOW_HOURS
                )
            ];
        }
        
        return ['valid' => true, 'error' => null];
        
    } catch (\Exception $e) {
        $this->logger->warning('Rate limit check failed', [
            'user_id' => $user->getId(),
            'error'   => $e->getMessage(),
        ]);
        return ['valid' => true, 'error' => null]; // Fail-open
    }
}

private function generateConversationHash(array $userIds, string $title): string
{
    // Normaliser le titre plus agressivement
    $normalizedTitle = trim(preg_replace('/\s+/', ' ', $title));
    $normalizedTitle = mb_strtolower($normalizedTitle, 'UTF-8');
    
    // Supprimer la ponctuation optionnelle pour plus de flexibilité
    $normalizedTitle = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalizedTitle);
    
    // Trier les IDs pour ordre cohérent
    sort($userIds);
    
    // Créer une chaîne unique
    $uniqueString = implode(',', $userIds) . '|' . $normalizedTitle;
    
    return hash('sha256', $uniqueString);
}

private function checkDuplicateConversation(User $creator, array $userIds, string $title, EntityManagerInterface $em): bool
{
    $conversationHash = $this->generateConversationHash(
        array_merge([$creator->getId()], $userIds),
        $title
    );
    
    // Vérifier si une conversation identique existe déjà
    // Si le champ conversationHash existe dans l'entité :
    try {
        $existing = $em->getRepository(Conversation::class)
            ->createQueryBuilder('c')
            ->where('c.conversationHash = :hash')
            ->setParameter('hash', $conversationHash)
            ->getQuery()
            ->getOneOrNullResult();
        
        return $existing !== null;
    } catch (\Exception $e) {
        $this->logger->warning('Error checking duplicate conversation', [
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}





    #[Route('/api/getConnectedUser', name: 'get_connected_user', methods: ['GET'])]
    public function getConnectedUser(): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse([
                    'message' => 'User not authenticated',
                    'debug' => 'No user found in security context'
                ], 401);
            }

            if (!$user instanceof User) {
                return new JsonResponse([
                    'message' => 'Invalid user type',
                    'debug' => 'User is not an instance of App\Entity\User'
                ], 500);
            }


    // ✅ Valider TOUS les champs de nom
    $firstName = $user->getFirstName();
    $lastName = $user->getLastName();
    
    if (!$this->validateName($firstName)) {
        $this->logger->warning('Invalid firstName', [
            'user_id'    => $user->getId(),
            'first_name' => $firstName,
        ]);
        return new JsonResponse([
            'message' => 'Invalid user data',
            'error' => 'First name contains invalid characters'
        ], 500);
    }
    
    if (!$this->validateName($lastName)) {

        $this->logger->warning('Invalid lastName', [
            'user_id'   => $user->getId(),
            'last_name' => $lastName,
        ]);
        return new JsonResponse([
            'message' => 'Invalid user data',
            'error' => 'Last name contains invalid characters'
        ], 500);
    }


                    // ✅ Validation de l'email avant de l'envoyer 
                    $email = $user->getEmail();
                    $emailValidation = $this->validateEmailandUniqueness($email, $user->getId(), $this->em);
                    if (!$emailValidation['valid']) {   
                    $this->logger->warning('Invalid email', [
                        'user_id' => $user->getId(),
                        'error'   => $emailValidation['error'],
                    ]);
                    return new JsonResponse([ 
                            'message' => 'Invalid user email format',
                            'error' => $emailValidation['error']
                        ], 500);
                    }


                    // ✅ AJOUT : Validation des dates de disponibilité avant envoi
                    $availabilityStart = null;
                    $availabilityEnd = null;

                    if ($user->getAvailabilityStart()) {
                        $startDateStr = $user->getAvailabilityStart()->format('Y-m-d');
                        
                        // Vérifier que la date est valide et cohérente
                        if ($this->validateAvailabilityDates($user->getAvailabilityStart(), $user->getAvailabilityEnd())['valid']) {
                            $availabilityStart = $startDateStr;
                        } else {
                            $this->logger->warning('Invalid availability start date', [
                                'user_id' => $user->getId(),
                                'date'    => $startDateStr,
                            ]);
                        }
                    }
                            if ($user->getAvailabilityEnd()) {
                        $endDateStr = $user->getAvailabilityEnd()->format('Y-m-d');
                        
                        if ($this->validateAvailabilityDates($user->getAvailabilityStart(), $user->getAvailabilityEnd())['valid']) {
                            $availabilityEnd = $endDateStr;
                        } else {
                            $this->logger->warning('Invalid availability end date', [
                                'user_id' => $user->getId(),
                                'date'    => $endDateStr,
                            ]);
                        }
                    }



            $conversations = [];
            foreach ($user->getConversations() as $conversation) {
                $conversations[] = [
                    'id' => $conversation->getId(),
                ];
            }

            return new JsonResponse([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d'),
                'userData' => $conversations,
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/api/get/conversations', name: 'user_conversations', methods: ['GET'])]
    public function getUserConversations(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $conversations = $em->getRepository(Conversation::class)
                ->createQueryBuilder('c')
                ->innerJoin('c.users', 'u')
                ->where('u.id = :userId')
                ->setParameter('userId', $user->getId())
                ->orderBy('c.lastMessageAt', 'DESC')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($conversations as $conversation) {

            // ✅ AJOUT : Valider le titre de la conversation
            $title = $conversation->getTitle();
            if (!$this->validateString($title, 255)) {

                $this->logger->warning('Invalid conversation title', [
                    'conversation_id' => $conversation->getId(),
                ]);
            continue; // Sauter cette conversation corrompue
            }

            // ✅ AJOUT : Valider la description 
            $description = $conversation->getDescription() ?? '';
            if ($description !== '' && !$this->validateString($description, 1000)) {
                $this->logger->warning('Invalid conversation description', [
                'conversation_id' => $conversation->getId(),
            ]);        
            $description = ''; // Réinitialiser si invalide
            }

                $users = [];
                foreach ($conversation->getUsers() as $convUser) {
                    if ($convUser->getId() !== $user->getId()) {

                            // ✅ AJOUT : Valider les noms des participants
                            $firstName = $convUser->getFirstName();
                            $lastName = $convUser->getLastName();
                            
                            if (!$this->validateName($firstName) || !$this->validateName($lastName)) {
                                $this->logger->warning('Invalid participant name', [
                                    'user_id'         => $convUser->getId(),
                                    'conversation_id' => $conversation->getId(),
                                ]);
                                continue; // Sauter cet utilisateur corrompu
                            }

                            // ✅ AJOUT : Valider l'email
                            $email = $convUser->getEmail();
                            $emailCheck = $this->validateEmailandUniqueness($email, $convUser->getId(), $em);
                            if (!$emailCheck['valid']) {
                                $this->logger->warning('Invalid participant email', [
                                    'user_id' => $convUser->getId(),
                                ]);
                                continue; // Sauter cet utilisateur
                            }

                        $users[] = [
                            'id' => $convUser->getId(),
                            'name' => $convUser->getFirstName() . ' ' . $convUser->getLastName(),
                            'email' => $convUser->getEmail()
                        ];
                    }
                }

            // ✅ RÈGLE MÉTIER : Ne pas retourner de conversation sans participants valides
            if (empty($users)) {
                $this->logger->warning('Conversation has no valid participants', [
                    'conversation_id' => $conversation->getId(),
                ]);
                continue;
            }

                $createdByName = $conversation->getCreatedBy() 
                    ? $conversation->getCreatedBy()->getFirstName() . ' ' . $conversation->getCreatedBy()->getLastName()
                    : 'Unknown';


            // ✅ AJOUT : Valider le nom du créateur
            if ($conversation->getCreatedBy()) {
                $creatorFirstName = $conversation->getCreatedBy()->getFirstName();
                $creatorLastName = $conversation->getCreatedBy()->getLastName();
                
                if (!$this->validateName($creatorFirstName) || !$this->validateName($creatorLastName)) {
                    $createdByName = 'Unknown';
                }
            }

                $data[] = [
                    'id' => $conversation->getId(),
                    'title' => $conversation->getTitle(),
                    'description' => $conversation->getDescription(),
                    'createdBy' => $createdByName,
                    'createdAt' => $conversation->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'lastMessageAt' => $conversation->getLastMessageAt()?->format('Y-m-d H:i:s'),
                    'users' => $users,
                    'userCount' => count($users),
                    'createdById' => $conversation->getCreatedBy()?->getId(),
                ];
            }

                    
                    // ✅ Forcer le nettoyage du cache Doctrine
                    $em->clear();


            return new JsonResponse($data);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Error fetching conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    




#[Route('/api/get/messages', name: 'get_messages', methods: ['GET'])]
public function getMessages(Security $security, MessageRepository $messageRepo, Request $request): JsonResponse
{
    // Vérifier que l'utilisateur est connecté
    $user = $security->getUser();
    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }

        // ✅ Utiliser les constantes pour la pagination
        $page = (int) $request->query->get('page', BusinessLimits::PAGINATION_MIN_PAGE);
        $limit = (int) $request->query->get('limit', BusinessLimits::PAGINATION_DEFAULT_LIMIT);

        // ✅ Validation avec limites documentées
        if ($page < BusinessLimits::PAGINATION_MIN_PAGE) {
            $page = BusinessLimits::PAGINATION_MIN_PAGE;
        }
        if ($page > BusinessLimits::PAGINATION_MAX_PAGE) {
            $page = BusinessLimits::PAGINATION_MAX_PAGE;
        }
        if ($limit < BusinessLimits::PAGINATION_MIN_LIMIT) {
            $limit = BusinessLimits::PAGINATION_MIN_LIMIT;
        }
        if ($limit > BusinessLimits::PAGINATION_MAX_LIMIT) {
            $limit = BusinessLimits::PAGINATION_MAX_LIMIT;
        }

        $offset = ($page - 1) * $limit;
        
        // ✅ AJOUT : Vérifier l'offset maximum absolu
        if ($offset > BusinessLimits::PAGINATION_MAX_OFFSET) {
            return new JsonResponse([
                'message' => 'Page number too high',
                'error' => sprintf(
                    'Maximum offset is %d (page %d with limit %d)',
                    BusinessLimits::PAGINATION_MAX_OFFSET,
                    BusinessLimits::PAGINATION_MAX_PAGE,
                    BusinessLimits::PAGINATION_MAX_LIMIT
                )
            ], 400);
        }

    // Récupérer les messages depuis la base de données
    $messages = $messageRepo->findBy(
        [],                          // Pas de filtre (tous les messages)
        ['createdAt' => 'DESC'],     // Trier du plus récent au plus ancien
        $limit,                       // Nombre de résultats
        $offset                       // Combien en sauter
    );

    // Compter le nombre total de messages
    $total = $messageRepo->count([]);

    // Calculer le nombre total de pages
    $totalPages = ($limit > 0) ? (int)ceil($total / $limit) : 0;

    // Construire le tableau de données pour la réponse
    $data = [];
    foreach ($messages as $message) {


    // ✅ AJOUT : Valider le contenu du message
            $content = $message->getContent();
            if (!$this->validateMessageContent($content)) {
                $this->logger->warning('Invalid message content', [
                    'message_id' => $message->getId(),
                ]);
                continue; // Sauter ce message corrompu
            }

            // ✅ AJOUT : Valider l'email de l'auteur
            $authorEmail = $message->getAuthor()->getEmail();
            $emailCheck = $this->validateEmailandUniqueness(
                $authorEmail, 
                $message->getAuthor()->getId(), 
                $this->em
            );
            if (!$emailCheck['valid']) {
                $this->logger->warning('Invalid author email', [
                    'message_id' => $message->getId(),
                ]);
                $authorEmail = 'unknown@invalid.com'; // Fallback sécurisé
            }

            // ✅ AJOUT : Valider le nom de l'auteur
            $authorName = $message->getAuthorName();
            // Le nom est au format "Prénom Nom"
            $nameParts = explode(' ', $authorName, 2);
            if (count($nameParts) === 2) {
                if (!$this->validateName($nameParts[0]) || !$this->validateName($nameParts[1])) {
                    $this->logger->warning('Invalid author name', [
                        'message_id' => $message->getId(),
                    ]);
                    $authorName = 'Unknown User';
                }
            } else {
                $authorName = 'Unknown User';
            }

            // ✅ AJOUT : Valider le titre de la conversation
            $conversationTitle = $message->getConversationTitle();
            if (!$this->validateString($conversationTitle, 255)) {
                $this->logger->warning('Invalid conversation title for message', [
                    'message_id' => $message->getId(),
                ]);
                $conversationTitle = 'Untitled Conversation';
            }

            // ✅ RÈGLE MÉTIER : Vérifier que la conversation existe toujours
            if (!$message->getConversation()) {
                $this->logger->warning('Message has no associated conversation', [
                    'message_id' => $message->getId(),
                ]);
                continue; // Sauter ce message orphelin
            }

            
        $data[] = [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'author' => $message->getAuthor()->getEmail(),
            'authorId' => $message->getAuthor()->getId(),
            'authorName' => $message->getAuthorName(),
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            'conversationId' => $message->getConversation()->getId(),
            'conversationTitle' => $message->getConversationTitle(),
        ];
    }

    // Retourner les données avec les informations de pagination
    return new JsonResponse([
        'data' => $data,                // Liste des messages
        'pagination' => [
            'page' => $page,            // Page actuelle
            'limit' => $limit,          // Résultats par page
            'total' => $total,          // Nombre total de messages
            'pages' => $totalPages      // Nombre total de pages
        ]
    ], 200);
}

#[Route('/api/create/conversation', name: 'create_conversation', methods: ['POST'])]
public function createConversation(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
{
    $user = $security->getUser();
    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }


     // ✅ ÉTAPE 1 : Valider l'état du compte utilisateur
    $userValidation = $this->validateUserState($user);
    if (!$userValidation['valid']) {
        return new JsonResponse([
            'message' => 'Cannot create conversation: invalid user state',
            'error' => $userValidation['error']
        ], 400);
    }


    // ✅ AJOUT : Validations AVANT la transaction
    $rateLimitCheck = $this->validateConversationCreateRateLimit($user, $em);
    if (!$rateLimitCheck['valid']) {
        return new JsonResponse(['message' => $rateLimitCheck['error']], 429);
    }
    
    // ✅ AJOUT : DÉBUT DE TRANSACTION
    $em->beginTransaction();

    try {

         // ✅ PROTECTION XXE : Vérifier le Content-Type
     // ✅ ÉTAPE 1 : Récupérer le Content-Type de la requête HTTP
        $contentType = $request->headers->get('Content-Type', '');
        // ✅ ÉTAPE 2 : Vérifier que ça commence par 'application/json'
        if (!str_starts_with($contentType, 'application/json')) {
            return new JsonResponse([
                'message' => 'Invalid Content-Type. Only application/json is accepted'
            ], 415); // 415 Unsupported Media Type
        }


        // ✅ ÉTAPE 1 : Parser le JSON (sans décodage)

        $rawContent = $request->getContent();

        // Protection contre les payloads massifs
        if (strlen($rawContent) > 10000) { // 100KB max
            return new JsonResponse(['message' => 'Request too large'], 413);
        }

        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['message' => 'Invalid JSON: '], 400);
        }
        $data = json_decode($rawContent, true, 10); // Max 10 niveaux de profondeur
        // Vérifier qu'il n'y a pas de champs interdits
        $allowedFields = ['title', 'description', 'conv_users'];
        $extraFields = array_diff(array_keys($data), $allowedFields);
        if (!empty($extraFields)) {
            throw new Exception('Champs interdits détectés');
        }

        // ✅ ÉTAPE 2 : VALIDATION PRÉLIMINAIRE (avant décodage/sanitization)
        if (empty($data['title']) || !is_string($data['title'])) {
            return new JsonResponse(['message' => 'Title is required and must be a string'], 400);
        }
        
        if (isset($data['description']) && !is_string($data['description'])) {
            return new JsonResponse(['message' => 'Description must be a string'], 400);
        }

        if (isset($data['conv_users']) && !is_array($data['conv_users'])) {
            return new JsonResponse(['message' => 'conv_users must be an array'], 400);
        }

        // ✅ ÉTAPE 3 : Décodage canonique (seulement si validation initiale OK)
        $data['title'] = $this->canonicalDecode($data['title']);
        
        if (isset($data['description']) && !empty($data['description'])) {
            $data['description'] = $this->canonicalDecode($data['description']);
        }

        // ✅ ÉTAPE 4 : Validation stricte APRÈS décodage
        if (!$this->validateString($data['title'], 255)) {
            return new JsonResponse([
                'message' => 'Invalid title format. Only letters, numbers, spaces, and basic punctuation are allowed. Maximum length is 255 characters.'
            ], 400);
        }

        if (isset($data['description']) && !empty($data['description']) && !$this->validateString($data['description'], 1000)) {
            return new JsonResponse([
                'message' => 'Invalid description format. Only letters, numbers, spaces, and basic punctuation are allowed. Maximum length is 1000 characters.'
            ], 400);
        }

        // ✅ AJOUT : Validation croisée titre/description
        if (!empty($data['description']) && $data['title'] === $data['description']) {
            return new JsonResponse([
                'message' => 'Title and description cannot be identical'
            ], 400);
        }

        // ✅ ÉTAPE 5 : Sanitization (dernière étape)
        $data = $this->sanitizeData($data);



        // ✅ ÉTAPE 6 : Typage fort et création de l'entité
        $conversation = new Conversation();

        $conversation->setTitle($data['title']);
        $conversation->setDescription($data['description'] ?? '');
        $conversation->setCreatedBy($user);
        $conversation->setCreatedAt(new \DateTimeImmutable());
        $conversation->setLastMessageAt(null);

        // Ajouter le créateur
        $conversation->addUser($user);

        // ✅ ÉTAPE 7 : Validation de cohérence des participants
            $conversationUsers = [];
            $userIdsToInvite = $data['conv_users'] ?? [];

            // Vérifier que c'est un tableau
            if (!is_array($userIdsToInvite)) {
                $userIdsToInvite = [];
            }

            // ✅ UTILISER le validateur
            $check = $this->validateConversationParticipants($user, $userIdsToInvite, $em);

            // Si erreur → arrêter et renvoyer l'erreur
            if (!$check['valid']) {
                return new JsonResponse(['message' => $check['error']], 400);
            }

            // Si OK → ajouter les utilisateurs validés
            foreach ($check['validUsers'] as $friendUser) {
                $conversation->addUser($friendUser);
                $conversationUsers[] = [
                    'id' => $friendUser->getId(),
                    'name' => $this->sanitizeForJson($friendUser->getFirstName() . ' ' . $friendUser->getLastName()),
                    'email' => $this->sanitizeForJson($friendUser->getEmail())
                ];

            }

            
        // ✅ AJOUT : VÉRIFICATION DES DOUBLONS 
        if ($this->checkDuplicateConversation($user, $userIdsToInvite, $data['title'], $em)) {
            $em->rollback();
            return new JsonResponse([
                'message' => 'A conversation with the same title already exists between these users',
                'error' => 'DUPLICATE_CONVERSATION'
            ], 409); // 409 Conflict
        }
        
            $conversationHash = $this->generateConversationHash(
                array_merge([$user->getId()], $userIdsToInvite),
                $data['title']
            );

            // Si votre entité Conversation a le champ conversationHash
            if (method_exists($conversation, 'setConversationHash')) {
                $conversation->setConversationHash($conversationHash);
            }

            
            // ✅ Libérer le tableau temporaire
            $check = null;
        

        // Persister la conversation
        $em->persist($conversation);
        $em->flush();





        // ✅ ÉTAPE 8 : Nettoyer les données pour la réponse JSON
        $response = [
            'id' => $conversation->getId(),
            'title' => $this->sanitizeForJson($conversation->getTitle()),
            'description' => $this->sanitizeForJson($conversation->getDescription()),
            'createdBy' => $this->sanitizeForJson($user->getFirstName() . ' ' . $user->getLastName()),
            'createdAt' => $conversation->getCreatedAt()->format('Y-m-d H:i:s'),
            'createdById' => $conversation->getCreatedBy()?->getId(),
            'lastMessageAt' => $conversation->getLastMessageAt(),
            'users' => $conversationUsers,
            'userCount' => count($conversationUsers)
        ];
        

        // ✅ Libérer les références locales
        $conversation = null;
        $conversationUsers = null;
        $data = null;
        $rawContent = null;
        
     // ✅ AJOUT : COMMIT si tout OK
        $em->commit();

        return new JsonResponse($response, 201);
        
    } catch (\InvalidArgumentException $e) {
        // ✅ AJOUT : ROLLBACK sur erreur validation (400)
        $em->rollback();
        $em->clear();
        return new JsonResponse(['message' => $e->getMessage()], 400);

        
    } catch (\Exception $e) {
        // ✅ AJOUT : ROLLBACK sur erreur système (500)
        $em->rollback();
        $em->clear();
        return new JsonResponse(['error' => $e->getMessage()], 500);
    }
}

#[Route('/api/create/message', name: 'create_message', methods: ['POST'])]
public function createMessage(
    Request $request, 
Security $security, 
EntityManagerInterface $em,
RateLimiterFactory $apiMessageLimiter): JsonResponse
{
    $user = $security->getUser();
    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }

        // ✅ Protection anti-automatisation OWASP 2.4.1
    $limiter = $apiMessageLimiter->create($user->getUserIdentifier());
    if (false === $limiter->consume(1)->isAccepted()) {
        return new JsonResponse([
            'message' => 'Too many requests. Please slow down.'
        ], 429);
    }

    try {
     // ✅ PROTECTION XXE : Vérifier le Content-Type
     // ✅ ÉTAPE 1 : Récupérer le Content-Type de la requête HTTP
        $contentType = $request->headers->get('Content-Type', '');
        // ✅ ÉTAPE 2 : Vérifier que ça commence par 'application/json'
        if (!str_starts_with($contentType, 'application/json')) {
            return new JsonResponse([
                'message' => 'Invalid Content-Type. Only application/json is accepted'
            ], 415); // 415 Unsupported Media Type
        }

        // ✅ ÉTAPE 1 : Parser le JSON (sans décodage)
        $rawContent = $request->getContent();

        // Protection contre les payloads massifs
        if (strlen($rawContent) > 10000) { // 100KB max
            return new JsonResponse(['message' => 'Request too large'], 413);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['message' => 'Invalid JSON: '], 400);
        }

        $data = json_decode($rawContent, true, 10); // Max 10 niveaux de profondeur

        // Vérifier qu'il n'y a pas de champs interdits
        $allowedFields = ['title', 'conversation_id', 'content'];
        $extraFields = array_diff(array_keys($data), $allowedFields);
        if (!empty($extraFields)) {
            throw new Exception('Champs interdits détectés');
        }
        
        // ✅ ÉTAPE 2 : VALIDATION PRÉLIMINAIRE (avant décodage/sanitization)
        if (empty($data['content']) || !is_string($data['content'])) {
            return new JsonResponse(['message' => 'Content is required and must be a string'], 400);
        }
        
        if (empty($data['conversation_id']) || !is_numeric($data['conversation_id'])) {
            return new JsonResponse(['message' => 'Conversation ID is required and must be numeric'], 400);
        }

        // ✅ ÉTAPE 3 : Décodage canonique (seulement si validation initiale OK)
        $data['content'] = $this->canonicalDecode($data['content']);
        
        if (isset($data['title'])) {
            $data['title'] = $this->canonicalDecode($data['title']);
        }

        // ✅ ÉTAPE 4 : Validation stricte APRÈS décodage
        if (!$this->validateMessageContent($data['content'])) {
            return new JsonResponse([
                'message' => 'Invalid content format. Only letters, numbers, spaces, and basic punctuation are allowed. Maximum length is 250 characters.'
            ], 400);
        }

        // ✅ ÉTAPE 5 : Sanitization (dernière étape)
        $data = $this->sanitizeData($data);

        // ✅ ÉTAPE 6 : Typage fort
        $conversationId = (int) $data['conversation_id'];
        
        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        
        if (!$conversation) {
            return new JsonResponse(['message' => 'Conversation not found'], 404);
        }

        // ✅ ÉTAPE 7 : Vérifier que l'utilisateur fait partie de la conversation
        if (!$conversation->getUsers()->contains($user)) {
            return new JsonResponse(['message' => 'You are not a member of this conversation'], 403);
        }


        // ✅ AJOUT : Vérifier le rate limit
        $rateLimitCheck = $this->validateMessageRateLimit($user, $conversation, $em);

        if (!$rateLimitCheck['valid']) {
            return new JsonResponse(['message' => $rateLimitCheck['error']], 429); // 429 Too Many Requests
        }


        $message = new Message();
        $message->setContent($data['content']);
        $message->setCreatedAt(new \DateTimeImmutable());
        $message->setConversation($conversation);
        $message->setAuthor($user);

        $em->persist($message);
        $em->flush();

        // ✅ ÉTAPE 8 : Nettoyer les données pour la réponse JSON
        return new JsonResponse([
            'id' => $message->getId(),
            'author' => $this->sanitizeForJson($message->getAuthor()->getEmail()),
            'authorName' => $this->sanitizeForJson($message->getAuthorName()),
            'content' => $this->sanitizeForJson($message->getContent()),
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            'conversationId' => $conversation->getId(),
            'conversationTitle' => $this->sanitizeForJson($conversation->getTitle()),
            'authorId' => $message->getAuthor()->getId(),
        ], 201);

    $em->clear();
        
    } catch (\Exception $e) {
        $em->rollback();
        $em->clear();
        
        $this->logger->error('Transaction failed in createMessage', [
            'error' => $e->getMessage(),
        ]);
        return new JsonResponse([
            'message' => 'Error creating message',
            'error' => $e->getMessage()
        ], 500);
    }
}

    #[Route('/api/delete/conversation/{id}', name: 'delete_conversation', methods: ['DELETE'])]
    public function deleteConversation(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        // ✅ AJOUT : Validation de l'ID (taille et plage valide)
        if ($id <= 0) {
            return new JsonResponse(['message' => 'Invalid conversation ID'], 400);
        }
        

        try {
            $conversation = $em->getRepository(Conversation::class)->find($id);

            if (!$conversation) {
                return new JsonResponse(['message' => 'Conversation not found'], 404);
            }

            // ✅ Vérifier la limite AVANT de supprimer les messages
            $messageCount = count($conversation->getMessages());
            if ($messageCount > BusinessLimits::CONVERSATION_MAX_MESSAGES_FOR_DELETE) {
                return new JsonResponse([
                    'message' => sprintf(
                        'Conversation too large to delete (%d messages). Maximum is %d. Please contact support.',
                        $messageCount,
                        BusinessLimits::CONVERSATION_MAX_MESSAGES_FOR_DELETE
                    )
                ], 413);
            }

        // ✅ AJOUT : Rate limiting sur la suppression
        $rateLimitCheck = $this->validateConversationDeleteRateLimit($user, $em);
        if (!$rateLimitCheck['valid']) {
            return new JsonResponse([
                'message' => $rateLimitCheck['error']
            ], 429);
        }

        // ✅ RÈGLE MÉTIER : Vérifier l'autorisation
        if ($conversation->getCreatedBy()->getId() !== $user->getId()) {
            return new JsonResponse(['message' => 'You are not authorized to delete this conversation'], 403);
        }

                    // ✅ AJOUT : Valider les données de la conversation avant suppression (audit)
                    $title = $conversation->getTitle();
                    if (!$this->validateString($title, 255)) {
                        $this->logger->warning('Deleting conversation with invalid title', [
                            'conversation_id' => $id,
                            'title'           => substr($title, 0, 50),
                        ]);
                    }

                    // ✅ AJOUT : Logger la suppression pour audit
                    $this->logger->info('Conversation deleted', [
                        'user_id'         => $user->getId(),
                        'conversation_id' => $conversation->getId(),
                        'title'           => substr($title, 0, 50),
                        'message_count'   => $messageCount,
                    ]);


            $messages = $em->getRepository(Message::class)->findBy(['conversation' => $conversation]);
            foreach ($messages as $message) {
                $em->remove($message);
            }

            // ✅ RÈGLE MÉTIER : Limiter le nombre de messages supprimables (protection DoS)
                if (count($messages) > 10000) {
                    return new JsonResponse([
                        'message' => 'Conversation too large to delete. Please contact support.'
                    ], 413); // 413 Payload Too Large
                }

                foreach ($messages as $message) {
                    $em->remove($message);
                }

            $em->remove($conversation);
            $em->flush();

            $em->clear();

        return new JsonResponse([
            'message' => 'Conversation deleted successfully',
            'deletedMessages' => $messageCount
        ], 200);
                

        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Error deleting conversation',
                'error' => $e->getMessage()
            ], 500);
                $em->clear();
        }
    }

    #[Route('/api/delete/message/{id}', name: 'delete_message', methods: ['DELETE'])]
    public function deleteMessage(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }


    // ✅ AJOUT : Validation TAILLE - ID valide
    if ($id <= 0) {
        return new JsonResponse(['message' => 'Invalid message ID'], 400);
    }

    // ✅ AJOUT : Validation TAILLE - Limite supérieure
    if ($id > 2147483647) { // Max int32
        return new JsonResponse(['message' => 'Message ID out of range'], 400);
    }


        try {
            $message = $em->getRepository(Message::class)->find($id);

            if (!$message) {
                return new JsonResponse(['message' => 'Message not found'], 404);
            }

            if ($message->getAuthor()->getId() !== $user->getId()) {
                return new JsonResponse(['message' => 'You are not authorized to delete this message'], 403);
            }


              // ✅ AJOUT : Validation TYPES - Vérifier l'instance
        if (!$message instanceof Message) {
            $this->logger->warning('Invalid message type', [
                'message_id' => $id,
            ]);

            return new JsonResponse(['message' => 'Invalid message data'], 500);
        }

        // ✅ RÈGLE MÉTIER : Autorisation
        if ($message->getAuthor()->getId() !== $user->getId()) {
            return new JsonResponse(['message' => 'You are not authorized to delete this message'], 403);
        }

        // ✅ AJOUT : Validation FORMAT - Vérifier l'intégrité avant suppression
        $content = $message->getContent();
        if (!$this->validateMessageContent($content)) {
            $this->logger->warning('Deleting message with invalid content', [
                'message_id' => $id,
            ]);
        }

        // ✅ AJOUT : Validation FORMAT - Vérifier l'auteur
        $author = $message->getAuthor();
        if (!$author instanceof User) {
            $this->logger->warning('Message has invalid author', [
                'message_id' => $id,
            ]);

            return new JsonResponse(['message' => 'Invalid message author'], 500);
        }

        // ✅ AJOUT : Validation MÉTIER - Vérifier que la conversation existe
        $conversation = $message->getConversation();
        if (!$conversation instanceof Conversation) {
            
        $this->logger->warning('Message has no valid conversation', [
            'message_id' => $id,
        ]);

            return new JsonResponse(['message' => 'Invalid message conversation'], 500);
        }
        $this->logger->info('Message deleted', [
            'user_id'         => $user->getId(),
            'message_id'      => $message->getId(),
            'conversation_id' => $conversation->getId(),
        ]);


            $em->remove($message);
            $em->flush();
        $em->clear();

            return new JsonResponse(['message' => 'Message deleted successfully'], 200);

        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Error deleting message',
                'error' => $e->getMessage()
            ], 500);
                $em->clear();
        }
    }





    
}