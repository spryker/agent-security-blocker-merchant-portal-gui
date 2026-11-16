<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\EventSubscriber;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\LoginRejectionResponseBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\MessageBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\SecurityCheckAuthContextBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class SecurityBlockerAgentMerchantPortalLoginEventSubscriber implements EventSubscriberInterface
{
    /**
     * @uses \Spryker\Zed\AgentSecurityMerchantPortalGui\Communication\Form\AgentMerchantLoginForm::FORM_NAME
     */
    protected const string FORM_NAME = 'agent-security-merchant-portal-gui';

    /**
     * @uses \Spryker\Zed\AgentSecurityMerchantPortalGui\Communication\Form\AgentMerchantLoginForm::FIELD_USERNAME
     */
    protected const string FIELD_USERNAME = 'username';

    protected const int KERNEL_REQUEST_CHECK_SUBSCRIBER_PRIORITY = 11;

    protected const int KERNEL_REQUEST_RESERVE_SUBSCRIBER_PRIORITY = 9;

    protected const string AUTHENTICATION_FAILURE_EVENT = 'onAuthenticationFailure';

    protected const string AUTHENTICATION_SUCCESS_EVENT = 'onAuthenticationSuccess';

    public function __construct(
        protected RequestStack $requestStack,
        protected AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface $securityBlockerClient,
        protected MessageBuilderInterface $messageBuilder,
        protected AgentSecurityBlockerMerchantPortalGuiConfig $agentSecurityBlockerMerchantPortalGuiConfig,
        protected SecurityCheckAuthContextBuilderInterface $securityCheckAuthContextBuilder,
        protected LoginRejectionResponseBuilderInterface $loginRejectionResponseBuilder
    ) {
    }

    /**
     * @return array<string, array<int, array<int, int|string>>|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => static::AUTHENTICATION_FAILURE_EVENT,
            LoginSuccessEvent::class => static::AUTHENTICATION_SUCCESS_EVENT,
            KernelEvents::REQUEST => [
                ['onKernelRequestCheckLoginAttempt', static::KERNEL_REQUEST_CHECK_SUBSCRIBER_PRIORITY],
                ['onKernelRequestReserveLoginAttempt', static::KERNEL_REQUEST_RESERVE_SUBSCRIBER_PRIORITY],
            ],
        ];
    }

    public function onAuthenticationFailure(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->isLoginAttempt($request)) {
            return;
        }

        $securityCheckAuthContextTransfer = $this->createSecurityCheckAuthContextTransfer($request);
        $this->securityBlockerClient->registerFailedLoginAttempt($securityCheckAuthContextTransfer);

        $loginIdentifier = $securityCheckAuthContextTransfer->getLoginIdentifier();

        if ($loginIdentifier === null || !$request->hasSession()) {
            return;
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $loginIdentifier);
    }

    public function onAuthenticationSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isLoginAttempt($request)) {
            return;
        }

        $this->securityBlockerClient->registerSuccessfulLoginAttempt($this->createSecurityCheckAuthContextTransfer($request));
    }

    public function onKernelRequestCheckLoginAttempt(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isLoginAttempt($request)) {
            return;
        }

        $securityCheckAuthResponseTransfer = $this->securityBlockerClient->checkLoginAttempt($this->createSecurityCheckAuthContextTransfer($request));

        if ($securityCheckAuthResponseTransfer->getIsBlocked()) {
            $this->rejectBlockedLoginAttempt($event, $securityCheckAuthResponseTransfer);
        }
    }

    public function onKernelRequestReserveLoginAttempt(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isLoginAttempt($request)) {
            return;
        }

        $securityCheckAuthResponseTransfer = $this->securityBlockerClient->reserveLoginAttempt($this->createSecurityCheckAuthContextTransfer($request));

        if ($securityCheckAuthResponseTransfer->getIsBlocked()) {
            $this->rejectBlockedLoginAttempt($event, $securityCheckAuthResponseTransfer);
        }
    }

    protected function isLoginAttempt(Request $request): bool
    {
        return $request->request->has(static::FORM_NAME);
    }

    protected function createSecurityCheckAuthContextTransfer(Request $request): SecurityCheckAuthContextTransfer
    {
        return $this->securityCheckAuthContextBuilder->createSecurityCheckAuthContextTransfer(
            $request,
            $this->findSubmittedField($request, static::FIELD_USERNAME),
        );
    }

    protected function findSubmittedField(Request $request, string $fieldName): ?string
    {
        $loginForm = $request->request->all()[static::FORM_NAME] ?? null;
        $value = is_array($loginForm) ? ($loginForm[$fieldName] ?? null) : null;

        return is_string($value) ? $value : null;
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function rejectBlockedLoginAttempt(RequestEvent $event, SecurityCheckAuthResponseTransfer $securityCheckAuthResponseTransfer): void
    {
        $response = $this->loginRejectionResponseBuilder->findBlockedLoginResponse(
            $event->getRequest(),
            $this->messageBuilder->getExceptionMessage($securityCheckAuthResponseTransfer),
            $this->agentSecurityBlockerMerchantPortalGuiConfig->getAgentMerchantPortalLoginUrl(),
        );

        if ($response === null) {
            throw $this->createTooManyRequestsException($securityCheckAuthResponseTransfer);
        }

        $event->setResponse($response);
    }

    protected function createTooManyRequestsException(SecurityCheckAuthResponseTransfer $securityCheckAuthResponseTransfer): HttpException
    {
        return new HttpException(
            Response::HTTP_TOO_MANY_REQUESTS,
            $this->messageBuilder->getExceptionMessage($securityCheckAuthResponseTransfer),
            null,
            [],
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }
}
