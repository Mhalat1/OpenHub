<?php

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthIntegrationTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container    = static::getContainer();

        $this->em     = $container->get(EntityManagerInterface::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);

        // Nettoie la table users avant chaque test
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    protected function tearDown(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers transversaux
    // ──────────────────────────────────────────────────────────────

    /**
     * Envoie une requête POST JSON et retourne [Response, array $data]
     */
    private function postJson(string $uri, array $body): array
    {
        $this->client->request(
            'POST',
            $uri,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body),
        );

        $response = $this->client->getResponse();
        $data     = json_decode($response->getContent(), true) ?? [];

        return [$response, $data];
    }

    /**
     * Crée un utilisateur directement en base (via Doctrine + Hasher)
     * pour préparer l'état initial d'un test.
     */
    private function seedUser(
        string $email      = 'seed@example.com',
        string $password   = 'Seed1234!',
        string $firstName  = 'John',
        string $lastName   = 'Doe',
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        // Détache l'entité : force Doctrine à relire la BDD lors des assertions
        $this->em->clear();

        return $user;
    }

    /**
     * Décode la payload d'un token JWT sans vérification de signature.
     * Permet de vérifier les claims directement dans les tests.
     */
    private function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }
        $payload = base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '='));
        return json_decode($payload, true) ?? [];
    }



    public function testRegisterIntegration_CreatesUserWithHashedPasswordInDatabase(): void
    {
        // ARRANGE — base vide

        // ACT — requête HTTP complète
        [$response, $data] = $this->postJson('/api/register', [
            'email'     => 'carol@example.com',
            'password'  => 'CarolSecret1!',
            'firstName' => 'Carol',
            'lastName'  => 'Martin',
        ]);

        // ASSERT couche HTTP + Controller
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertTrue($data['status']);

        // ASSERT couche Doctrine — on relit depuis la BDD (em->clear() pour éviter le cache)
        $this->em->clear();
        $savedUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => 'carol@example.com']);

        $this->assertNotNull($savedUser, 'L\'utilisateur doit être persisté en base');
        $this->assertSame('Carol', $savedUser->getFirstName());
        $this->assertSame('Martin', $savedUser->getLastName());

        // ASSERT couche Hasher — le mot de passe est hashé, pas stocké en clair
        $this->assertNotSame('CarolSecret1!', $savedUser->getPassword());
        $this->assertTrue(
            $this->hasher->isPasswordValid($savedUser, 'CarolSecret1!'),
            'Le hash stocké doit correspondre au mot de passe en clair',
        );

        // ASSERT couche Controller — l'ID retourné correspond à l'entité en base
        $this->assertSame($savedUser->getId(), $data['user']['id']);
    }

    // ══════════════════════════════════════════════════════════════
    // INTÉGRATION 5 : Controller → Doctrine (unicité email) → HTTP
    //
    // Scénario : deux requêtes d'inscription avec le même email.
    // Vérifie que le repository détecte le doublon et que le controller
    // retourne 409 sans créer un second enregistrement en base.
    // ══════════════════════════════════════════════════════════════

    public function testRegisterIntegration_DuplicateEmail_RepositoryBlocksAndReturns409(): void
    {
        // ARRANGE — un utilisateur déjà en base
        $this->seedUser('dave@example.com', 'DavePass1!');

        // ACT — tentative d'inscription avec le même email
        [$response, $data] = $this->postJson('/api/register', [
            'email'     => 'dave@example.com',
            'password'  => 'AnotherPass1!',
            'firstName' => 'Dave',
            'lastName'  => 'Clone',
        ]);

        // ASSERT — 409 et un seul utilisateur en base
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('already in use', $data['message']);

        $count = $this->em->getRepository(User::class)->count(['email' => 'dave@example.com']);
        $this->assertSame(1, $count, 'Un seul utilisateur doit exister malgré la double tentative');
    }

    // ══════════════════════════════════════════════════════════════
    // INTÉGRATION 6 : Controller → DateTimeImmutable → Doctrine → BDD
    //
    // Scénario : les dates d'availability passent par la conversion
    // DateTimeImmutable dans le controller, sont persistées via Doctrine,
    // puis relues depuis la BDD pour vérifier le round-trip complet.
    // ══════════════════════════════════════════════════════════════

    public function testRegisterIntegration_AvailabilityDates_PersistedAndReadBackFromDatabase(): void
    {
        // ACT
        [$response, $data] = $this->postJson('/api/register', [
            'email'             => 'avail@example.com',
            'password'          => 'Avail1234!',
            'firstName'         => 'Eve',
            'lastName'          => 'Schedule',
            'availabilityStart' => '2026-04-01',
            'availabilityEnd'   => '2026-08-31',
        ]);

        // ASSERT HTTP
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame('2026-04-01', $data['user']['availabilityStart']);
        $this->assertSame('2026-08-31', $data['user']['availabilityEnd']);

        // ASSERT BDD — round-trip Doctrine complet
        $this->em->clear();
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => 'avail@example.com']);

        $this->assertNotNull($user);
        $this->assertSame('2026-04-01', $user->getAvailabilityStart()->format('Y-m-d'));
        $this->assertSame('2026-08-31', $user->getAvailabilityEnd()->format('Y-m-d'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getAvailabilityStart());
    }

    // ══════════════════════════════════════════════════════════════
    // INTÉGRATION 7 : Routing → Controller → Validation → Doctrine
    //
    // Scénario : une date invalide est envoyée. Vérifie que le catch
    // du controller intercepte l'exception avant tout appel Doctrine,
    // et qu'aucune donnée corrompue n'est persistée en base.
    // ══════════════════════════════════════════════════════════════

    public function testRegisterIntegration_InvalidDate_ControllerCatchesBeforeDoctrinePersist(): void
    {
        [$response, $data] = $this->postJson('/api/register', [
            'email'             => 'baddate@example.com',
            'password'          => 'Pass1234!',
            'firstName'         => 'Frank',
            'lastName'          => 'Error',
            'availabilityStart' => 'not-a-valid-date',
        ]);

        // ASSERT HTTP
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertFalse($data['status']);

        // ASSERT Doctrine — rien ne doit avoir été persisté
        $this->em->clear();
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => 'baddate@example.com']);
        $this->assertNull($user, 'Aucun utilisateur ne doit être créé si les données sont invalides');
    }

}