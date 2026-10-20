<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

class PaymentGatewayManager
{
    protected Application $app;
    protected array $customCreators = [];
    protected array $drivers = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a payment gateway instance by name or default.
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = strtolower($name ?: $this->getDefaultGateway());

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Register a custom gateway creator Closure.
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[strtolower($driver)] = $callback;

        return $this;
    }

    /**
     * Resolve the given gateway.
     */
    protected function resolve(string $name): PaymentGatewayInterface
    {
        // 1. Check custom creators (allows runtime extension)
        if (isset($this->customCreators[$name])) {
            return $this->customCreators[$name]($this->app);
        }

        // 2. Check config
        $config = $this->app['config']->get("payments.gateways.{$name}");

        if (! $config || empty($config['driver'])) {
            $supported = implode(', ', array_keys($this->app['config']->get('payments.gateways', [])));
            throw new PaymentGatewayException("Payment gateway [{$name}] is not supported. Supported gateways are: {$supported}", 422);
        }

        $driverClass = $config['driver'];

        if (! class_exists($driverClass)) {
            throw new PaymentGatewayException("Gateway driver class [{$driverClass}] does not exist.", 500);
        }

        $instance = new $driverClass($config);

        if (! $instance instanceof PaymentGatewayInterface) {
            throw new PaymentGatewayException("Gateway driver [{$driverClass}] must implement ".PaymentGatewayInterface::class, 500);
        }

        return $instance;
    }

    /**
     * Get the default gateway name.
     */
    public function getDefaultGateway(): string
    {
        return $this->app['config']->get('payments.default', 'credit_card');
    }

    /**
     * Get list of all available gateways configured.
     */
    public function getAvailableGateways(): array
    {
        $configured = array_keys($this->app['config']->get('payments.gateways', []));
        $custom = array_keys($this->customCreators);

        return array_unique(array_merge($configured, $custom));
    }
}
