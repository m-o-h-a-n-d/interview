<?php

namespace Tests\Unit;

use App\DTOs\PaymentResultDTO;
use App\DTOs\ProcessPaymentDTO;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Drivers\CreditCardPaymentGateway;
use App\Services\Payment\Drivers\PayPalPaymentGateway;
use App\Services\Payment\Drivers\StripePaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Tests\TestCase;

class PaymentGatewayStrategyTest extends TestCase
{
    public function test_manager_resolves_credit_card_gateway(): void
    {
        /** @var PaymentGatewayManager $manager */
        $manager = app(PaymentGatewayManager::class);

        $gateway = $manager->gateway('credit_card');

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertInstanceOf(CreditCardPaymentGateway::class, $gateway);
        $this->assertEquals('credit_card', $gateway->getName());
    }

    public function test_manager_resolves_paypal_gateway(): void
    {
        /** @var PaymentGatewayManager $manager */
        $manager = app(PaymentGatewayManager::class);

        $gateway = $manager->gateway('paypal');

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertInstanceOf(PayPalPaymentGateway::class, $gateway);
        $this->assertEquals('paypal', $gateway->getName());
    }

    public function test_manager_resolves_stripe_gateway(): void
    {
        /** @var PaymentGatewayManager $manager */
        $manager = app(PaymentGatewayManager::class);

        $gateway = $manager->gateway('stripe');

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertInstanceOf(StripePaymentGateway::class, $gateway);
        $this->assertEquals('stripe', $gateway->getName());
    }

    public function test_manager_allows_dynamic_gateway_extension(): void
    {
        /** @var PaymentGatewayManager $manager */
        $manager = app(PaymentGatewayManager::class);

        // Dynamically add a custom Cryptocurrency gateway strategy
        $manager->extend('crypto', function () {
            return new class implements PaymentGatewayInterface {
                public function getName(): string
                {
                    return 'crypto';
                }

                public function process(ProcessPaymentDTO $dto): PaymentResultDTO
                {
                    return PaymentResultDTO::success(
                        transactionId: 'btc_tx_mock_123',
                        gatewayName: 'crypto',
                        message: 'Bitcoin blockchain transaction confirmed.'
                    );
                }
            };
        });

        $gateway = $manager->gateway('crypto');

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('crypto', $gateway->getName());

        $result = $gateway->process(new ProcessPaymentDTO(
            orderId: 1,
            paymentMethod: 'crypto',
            amount: 500.00
        ));

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(PaymentStatus::SUCCESSFUL, $result->status);
        $this->assertEquals('btc_tx_mock_123', $result->transactionId);
    }

    public function test_manager_throws_exception_for_unsupported_gateway(): void
    {
        $this->expectException(PaymentGatewayException::class);

        /** @var PaymentGatewayManager $manager */
        $manager = app(PaymentGatewayManager::class);
        $manager->gateway('non_existing_gateway_xyz');
    }
}
