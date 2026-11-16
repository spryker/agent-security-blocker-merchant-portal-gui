<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\EventSubscriber;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\MessageBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Checker\SymfonyVersionChecker;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class SecurityBlockerAgentMerchantPortalEventSubscriber implements EventSubscriberInterface
{
    /**
     * @uses \Spryker\Zed\AgentSecurityMerchantPortalGui\Communication\Form\MerchantAgentLoginForm::FORM_NAME
     *
     * @var string
     */
    protected const FORM_NAME = 'agent-security-merchant-portal-gui';

    /**
     * @uses \Spryker\Zed\AgentSecurityMerchantPortalGui\Communication\Form\MerchantAgentLoginForm::FIELD_AGENT_NAME
     *
     * @var string
     */
    protected const FIELD_AGENT_NAME = 'agent_name';

    /**
     * @uses \Spryker\Zed\AgentSecurityMerchantPortalGui\Communication\Form\AgentMerchantLoginForm::FIELD_USERNAME
     */
    protected const string FIELD_USERNAME = 'username';

    /**
     * @var string
     */
    protected const ROUTE_KEY = '_route';

    /**
     * Runs before the CAPTCHA check of the Captcha module (priority 10), so a blocked login is answered before its CAPTCHA is used.
     *
     * @var int
     */
    protected const KERNEL_REQUEST_SUBSCRIBER_PRIORITY = 11;

    /**
     * @var string
     */
    protected const AUTHENTICATION_FAILURE_EVENT = 'onAuthenticationFailure';

    protected RequestStack $requestStack;

    protected AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface $securityBlockerClient;

    protected AgentSecurityBlockerMerchantPortalGuiConfig $agentSecurityBlockerMerchantPortalGuiConfig;

    protected MessageBuilderInterface $messageBuilder;

    public function __construct(
        RequestStack $requestStack,
        AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface $securityBlockerClient,
        MessageBuilderInterface $messageBuilder,
        AgentSecurityBlockerMerchantPortalGuiConfig $agentSecurityBlockerMerchantPortalGuiConfig
    ) {
        $this->requestStack = $requestStack;
        $this->securityBlockerClient = $securityBlockerClient;
        $this->messageBuilder = $messageBuilder;
        $this->agentSecurityBlockerMerchantPortalGuiConfig = $agentSecurityBlockerMerchantPortalGuiConfig;
    }

    /**
     * @return array<int|string, array<int, int|string>|string>
     */
    public static function getSubscribedEvents(): array
    {
        $subscribedEvents = [
            LoginFailureEvent::class => static::AUTHENTICATION_FAILURE_EVENT,
            KernelEvents::REQUEST => ['onKernelRequest', static::KERNEL_REQUEST_SUBSCRIBER_PRIORITY],
        ];

        if (SymfonyVersionChecker::isSymfonyVersion5()) {
            $subscribedEvents[AuthenticationEvents::AUTHENTICATION_FAILURE] = static::AUTHENTICATION_FAILURE_EVENT;
        }

        return $subscribedEvents;
    }

    public function onAuthenticationFailure(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->isLoginAttempt($request)) {
            return;
        }

        $securityCheckAuthContextTransfer = $this->createSecurityCheckAuthContextTransfer($request);
        $this->securityBlockerClient->incrementLoginAttemptCount($securityCheckAuthContextTransfer);
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isLoginAttempt($request)) {
            return;
        }

        $securityCheckAuthContextTransfer = $this->createSecurityCheckAuthContextTransfer($request);
        $securityCheckAuthResponseTransfer = $this->securityBlockerClient->isAccountBlocked($securityCheckAuthContextTransfer);

        if (!$securityCheckAuthResponseTransfer->getIsBlocked()) {
            return;
        }

        $exceptionMessage = $this->messageBuilder->getExceptionMessage($securityCheckAuthResponseTransfer);

        throw new HttpException(
            Response::HTTP_TOO_MANY_REQUESTS,
            $exceptionMessage,
            null,
            [],
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }

    protected function isLoginAttempt(Request $request): bool
    {
        $currentRoute = $request->attributes->get(static::ROUTE_KEY);
        $configuredRoute = $this->agentSecurityBlockerMerchantPortalGuiConfig->getAgentMerchantPortalLoginCheckUrl();

        return $currentRoute === $configuredRoute
            && $request->getMethod() === Request::METHOD_POST;
    }

    protected function createSecurityCheckAuthContextTransfer(Request $request): SecurityCheckAuthContextTransfer
    {
        return (new SecurityCheckAuthContextTransfer())
            ->setType($this->agentSecurityBlockerMerchantPortalGuiConfig->getSecurityBlockerAgentMerchantPortalEntityType())
            ->setIp($request->getClientIp())
            ->setLoginIdentifier($this->findSubmittedUsername($request))
            ->setIpCounterKeyStrategy($this->agentSecurityBlockerMerchantPortalGuiConfig->getIpCounterKeyStrategy());
    }

    protected function findSubmittedUsername(Request $request): ?string
    {
        $loginForm = $request->request->all()[static::FORM_NAME] ?? null;
        $username = is_array($loginForm) ? ($loginForm[static::FIELD_USERNAME] ?? null) : null;

        return is_string($username) ? $username : null;
    }
}
