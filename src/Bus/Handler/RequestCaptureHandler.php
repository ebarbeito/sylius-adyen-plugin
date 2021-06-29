<?php

declare(strict_types=1);

namespace BitBag\SyliusAdyenPlugin\Bus\Handler;

use BitBag\SyliusAdyenPlugin\Bus\Command\RequestCapture;
use BitBag\SyliusAdyenPlugin\Provider\AdyenClientProvider;
use BitBag\SyliusAdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Webmozart\Assert\Assert;

class RequestCaptureHandler implements MessageHandlerInterface
{
    use GatewayConfigFromPaymentTrait;

    /** @var AdyenClientProvider */
    private $adyenClientProvider;

    public function __construct(AdyenClientProvider $adyenClientProvider)
    {
        $this->adyenClientProvider = $adyenClientProvider;
    }

    private function isCompleted(OrderInterface $order): bool
    {
        return $order->getPaymentState() == PaymentInterface::STATE_COMPLETED;
    }

    private function isAdyenPayment(PaymentInterface $payment): bool
    {
        /**
         * @var ?PaymentMethodInterface $method
         */
        $method = $payment->getMethod();
        if (
            $method === null
            || $method->getGatewayConfig() === null
            || !isset($this->getGatewayConfig($method)->getConfig()[AdyenClientProvider::FACTORY_NAME])
        ) {
            return false;
        }

        return true;
    }

    private function getPayment(OrderInterface $order): ?PaymentInterface
    {
        if ($this->isCompleted($order)) {
            return null;
        }

        $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);
        if ($payment === null) {
            return null;
        }

        return $payment;
    }

    public function __invoke(RequestCapture $requestCapture): void
    {
        $payment = $this->getPayment($requestCapture->getOrder());

        if ($payment === null || !$this->isAdyenPayment($payment)) {
            return;
        }

        $details = $payment->getDetails();
        if (!isset($details['pspReference'])) {
            return;
        }

        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $client = $this->adyenClientProvider->getForPaymentMethod($method);
        $client->requestCapture(
            (string) $details['pspReference'],
            $requestCapture->getOrder()->getTotal(),
            (string) $requestCapture->getOrder()->getCurrencyCode()
        );
    }
}
