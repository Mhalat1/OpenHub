<?php

namespace App\Controller;

use App\Service\AxiomService;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DonationController extends AbstractController
{
    public function __construct(
        private AxiomService $AxiomLogger,
    ) {}

    #[Route('/api/donate', name: 'donation', methods: ['POST'])]
    public function donate(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $amount = isset($data['amount']) ? (int)$data['amount'] * 100 : 500;

        $this->AxiomLogger->info('Donation attempt', [
            'amount_cents' => $amount,
        ]);

        try {
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'eur',
                        'product_data' => [
                            'name' => 'Don pour soutenir open-hub ❤️',
                        ],
                        'unit_amount'  => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode'        => 'payment',
                'success_url' => 'https://ton-site.fr/don/success',
                'cancel_url'  => 'https://ton-site.fr/don/cancel',
            ]);

            $this->AxiomLogger->info('✅ Stripe session created', [
                'session_id'   => $session->id,
                'amount_cents' => $amount,
            ]);

            return new JsonResponse(['url' => $session->url]);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Stripe session creation failed', [
                'amount_cents' => $amount,
                'error'        => $e->getMessage(),
            ]);

            return new JsonResponse([
                'message' => 'Error creating donation session',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}