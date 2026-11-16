<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\EventSubscriber;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\MessageTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Generated\Shared\Transfer\ZedUiFormResponseTransfer;
use Spryker\Shared\ZedUi\Configuration\ZedUiFormResponseBuilderInterface;
use Spryker\Shared\ZedUi\ZedUiFactoryInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\LoginRejectionResponseBuilder;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\MessageBuilder;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\SecurityCheckAuthContextBuilder;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\EventSubscriber\SecurityBlockerAgentMerchantPortalLoginEventSubscriber;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface;
use Spryker\Zed\Messenger\Business\MessengerFacadeInterface;
use SprykerTest\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiCommunicationTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group AgentSecurityBlockerMerchantPortalGui
 * @group Communication
 * @group EventSubscriber
 * @group SecurityBlockerAgentMerchantPortalLoginEventSubscriberTest
 * Add your own group annotations below this line
 */
class SecurityBlockerAgentMerchantPortalLoginEventSubscriberTest extends Unit
{
    protected const string ROUTE_OTHER = 'dashboard';

    /**
     * @uses \Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig::SECURITY_BLOCKER_AGENT_MERCHANT_PORTAL_ENTITY_TYPE
     *
     * @var string
     */
    protected const AGENT_MERCHANT_PORTAL_TYPE = 'agent-merchant-portal';

    /**
     * @uses \Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig::AGENT_MERCHANT_PORTAL_LOGIN_CHECK_URL
     *
     * @var string
     */
    protected const LOGIN_CHECK_URL = 'agent-security-merchant-portal-gui_login_check';

    /**
     * @uses \Spryker\Zed\Captcha\Communication\EventSubscriber\LoginCaptchaEventSubscriber::KERNEL_REQUEST_SUBSCRIBER_PRIORITY
     */
    protected const int CAPTCHA_SUBSCRIBER_PRIORITY = 10;

    protected const string CAPTCHA_REJECTION_URL = '/captcha-rejected';

    protected AgentSecurityBlockerMerchantPortalGuiCommunicationTester $tester;

    protected SecurityBlockerAgentMerchantPortalLoginEventSubscriber $subscriber;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface
     */
    protected AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface $securityBlockerClientMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\HttpFoundation\RequestStack
     */
    protected RequestStack $requestStackMock;

    public function testBlockedLoginAttemptIsRejectedBeforeItsCaptchaIsVerified(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $this->securityBlockerClientMock->method('checkLoginAttempt')->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(true)->setBlockedFor(360));
        $eventDispatcher = $this->createEventDispatcherWithCaptchaListener($this->createFailingCaptchaListener());

        // Assert
        $this->expectException(HttpException::class);

        // Act
        $eventDispatcher->dispatch($this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_POST), KernelEvents::REQUEST);
    }

    public function testLoginAttemptRejectedByTheCaptchaIsNotReserved(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $this->securityBlockerClientMock->method('checkLoginAttempt')->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(false));
        $event = $this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_POST);

        // Assert
        $this->securityBlockerClientMock->expects($this->never())->method('reserveLoginAttempt');

        // Act
        $this->createEventDispatcherWithCaptchaListener($this->createRejectingCaptchaListener())->dispatch($event, KernelEvents::REQUEST);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestStackMock = $this->createRequestStackMock();
        $this->securityBlockerClientMock = $this->createSecurityBlockerClientMock();
        $this->subscriber = $this->createSecurityBlockerAgentMerchantPortalLoginEventSubscriber();
    }

    public function testSecurityBlockerAgentMerchantPortalLoginEventSubscriberShouldCallAgentSecurityBlockerClientOnKernalReqeustWhileSendingValidLoginAttempt(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $securityCheckAuthResponseTransfer = (new SecurityCheckAuthResponseTransfer())->setIsBlocked(false);

        // Assert
        $this->securityBlockerClientMock->expects($this->once())
            ->method('checkLoginAttempt')
            ->with($securityCheckAuthContextTransfer)
            ->willReturn($securityCheckAuthResponseTransfer);

        // Act
        $event = $this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_POST);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->subscriber);
        $eventDispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testSecurityBlockerAgentMerchantPortalLoginEventSubscriberShouldThrowHttpExceptionWhileSendingBlockedLoginAttempt(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $securityCheckAuthResponseTransfer = (new SecurityCheckAuthResponseTransfer())->setIsBlocked(true);

        // Assert
        $this->securityBlockerClientMock->expects($this->once())
            ->method('checkLoginAttempt')
            ->with($securityCheckAuthContextTransfer)
            ->willReturn($securityCheckAuthResponseTransfer);
        $this->expectException(HttpException::class);

        // Act
        $event = $this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_POST);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->subscriber);
        $eventDispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testSecurityBlockerAgentMerchantPortalLoginEventSubscriberShouldNotCallAgentSecurityBlockerClientWhileSendingWrongRequest(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $securityCheckAuthResponseTransfer = (new SecurityCheckAuthResponseTransfer())->setIsBlocked(false);

        // Assert
        $this->securityBlockerClientMock->expects($this->never())
            ->method('checkLoginAttempt')
            ->with($securityCheckAuthContextTransfer)
            ->willReturn($securityCheckAuthResponseTransfer);

        // Act
        $event = $this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_GET);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->subscriber);
        $eventDispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testSecurityBlockerAgentMerchantPortalLoginEventSubscriberShouldReturnAgentSecurityBlockerClientExceptionWhileFailedLoginAttempt(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);

        // Assert
        $this->securityBlockerClientMock->expects($this->once())
            ->method('registerFailedLoginAttempt')
            ->with($securityCheckAuthContextTransfer);
        $this->requestStackMock->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($this->tester->createLoginRequest(Request::METHOD_POST, $securityCheckAuthContextTransfer));

        // Act
        $event = $this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_POST);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->subscriber);
        $eventDispatcher->dispatch($event, LoginFailureEvent::class);
    }

    public function testSecurityBlockerAgentMerchantPortalLoginEventSubscriberShouldNotCallAgentSecurityBlockerClientWhileSendingValidGetRequest(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);

        // Assert
        $this->securityBlockerClientMock->expects($this->never())
            ->method('registerFailedLoginAttempt')
            ->with($securityCheckAuthContextTransfer);
        $this->requestStackMock->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($this->tester->createLoginRequest(Request::METHOD_GET, $securityCheckAuthContextTransfer));

        // Act
        $event = $this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_POST);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->subscriber);
        $eventDispatcher->dispatch($event, LoginFailureEvent::class);
    }

    public function testLoginAttemptBeyondTheReservedAccountLimitIsRejectedWithTooManyRequests(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $this->securityBlockerClientMock->method('checkLoginAttempt')->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(false));

        // Assert
        $this->securityBlockerClientMock->expects($this->once())
            ->method('reserveLoginAttempt')
            ->with($securityCheckAuthContextTransfer)
            ->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(true)->setBlockedFor(360));
        $this->expectException(HttpException::class);

        // Act
        $this->dispatchKernelRequest($this->createRequestEventForMethod($securityCheckAuthContextTransfer, Request::METHOD_POST));
    }

    /**
     * The authenticator reads the POST body only, so the blocker must never count the identifier of a query-string decoy.
     */
    public function testIdentifierOfAQueryStringDecoyIsIgnored(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $request = $this->tester->createLoginRequest(Request::METHOD_POST, $securityCheckAuthContextTransfer);
        $request->query->set('username', 'decoy@example.com');
        $this->securityBlockerClientMock->method('checkLoginAttempt')->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(false));

        // Assert
        $this->securityBlockerClientMock->expects($this->once())
            ->method('reserveLoginAttempt')
            ->with($this->callback(fn (SecurityCheckAuthContextTransfer $contextTransfer): bool => $contextTransfer->getLoginIdentifier() === $securityCheckAuthContextTransfer->getLoginIdentifier()
                && $contextTransfer->getRealm() === AgentSecurityBlockerMerchantPortalGuiCommunicationTester::LOGIN_REALM_USER))
            ->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(false));

        // Act
        $this->dispatchKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testFailedLoginRemembersTheIdentifierForTheNextForm(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $request = $this->tester->createLoginRequest(Request::METHOD_POST, $securityCheckAuthContextTransfer);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStackMock->method('getCurrentRequest')->willReturn($request);

        // Act
        $this->subscriber->onAuthenticationFailure();

        // Assert
        $this->assertSame($securityCheckAuthContextTransfer->getLoginIdentifier(), $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME));
    }

    public function testSuccessfulLoginResetsTheAccountCounter(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $request = $this->tester->createLoginRequest(Request::METHOD_POST, $securityCheckAuthContextTransfer);

        // Assert
        $this->securityBlockerClientMock->expects($this->once())
            ->method('registerSuccessfulLoginAttempt')
            ->with($securityCheckAuthContextTransfer);

        // Act
        $this->subscriber->onAuthenticationSuccess(new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge((string)$securityCheckAuthContextTransfer->getLoginIdentifier())),
            $this->createMock(TokenInterface::class),
            $request,
            null,
            'main',
        ));
    }

    /**
     * The login form authenticator accepts the form on every route of its firewall, so the attempt is throttled wherever
     * the form is posted, not only on the login-check route.
     */
    public function testLoginFormPostedToAnotherRouteIsCheckedAndReserved(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE);
        $request = $this->tester->createLoginRequest(Request::METHOD_POST, $securityCheckAuthContextTransfer);
        $request->attributes->set('_route', static::ROUTE_OTHER);

        // Assert
        $this->securityBlockerClientMock->expects($this->once())
            ->method('checkLoginAttempt')
            ->with($securityCheckAuthContextTransfer)
            ->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(false));
        $this->securityBlockerClientMock->expects($this->once())
            ->method('reserveLoginAttempt')
            ->with($securityCheckAuthContextTransfer)
            ->willReturn((new SecurityCheckAuthResponseTransfer())->setIsBlocked(false));

        // Act
        $this->dispatchKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $this->getMainRequestType()));
    }

    public function testLoginCheckRequestWithoutTheLoginFormIsIgnored(): void
    {
        // Arrange
        $request = $this->tester->createLoginRequest(Request::METHOD_POST, $this->tester->createLoginSecurityCheckAuthContextTransfer(static::AGENT_MERCHANT_PORTAL_TYPE));
        $request->request->replace([]);

        // Assert
        $this->securityBlockerClientMock->expects($this->never())->method('checkLoginAttempt');

        // Act
        $this->dispatchKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $this->getMainRequestType()));
    }

    public function testBlockedJsonLoginSubmissionIsAnsweredWithTheLockMessageAndARedirectAction(): void
    {
        // Arrange
        $messengerFacadeMock = $this->createMock(MessengerFacadeInterface::class);
        $request = Request::create('/login_check', Request::METHOD_POST, [], [], [], ['HTTP_ACCEPT' => 'application/json, text/plain, */*']);

        // Assert
        $messengerFacadeMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(fn (MessageTransfer $messageTransfer): bool => $messageTransfer->getValue() === 'Too many login attempts.'));

        // Act
        $response = (new LoginRejectionResponseBuilder($messengerFacadeMock, $this->createZedUiFactoryMock()))
            ->findBlockedLoginResponse($request, 'Too many login attempts.', '/login');

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /**
     * A regular form submission, and the Back Office application without a ZedUi factory, keep the 429 error page.
     */
    public function testBlockedLoginSubmissionWithoutZedUiJsonSupportIsLeftToTheTooManyRequestsPage(): void
    {
        // Arrange
        $jsonRequest = Request::create('/login_check', Request::METHOD_POST, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $htmlRequest = Request::create('/login_check', Request::METHOD_POST, [], [], [], ['HTTP_ACCEPT' => 'text/html']);

        // Act
        $responseWithoutFactory = (new LoginRejectionResponseBuilder($this->createMock(MessengerFacadeInterface::class), null))
            ->findBlockedLoginResponse($jsonRequest, 'Too many login attempts.', '/login');
        $responseForHtml = (new LoginRejectionResponseBuilder($this->createMock(MessengerFacadeInterface::class), $this->createZedUiFactoryMock()))
            ->findBlockedLoginResponse($htmlRequest, 'Too many login attempts.', '/login');

        // Assert
        $this->assertNull($responseWithoutFactory);
        $this->assertNull($responseForHtml);
    }

    protected function createZedUiFactoryMock(): ZedUiFactoryInterface
    {
        $zedUiFormResponseBuilderMock = $this->createMock(ZedUiFormResponseBuilderInterface::class);
        $zedUiFormResponseBuilderMock->method('addActionRedirect')->willReturnSelf();
        $zedUiFormResponseBuilderMock->method('createResponse')->willReturn(new ZedUiFormResponseTransfer());

        $zedUiFactoryMock = $this->createMock(ZedUiFactoryInterface::class);
        $zedUiFactoryMock->method('createZedUiFormResponseBuilder')->willReturn($zedUiFormResponseBuilderMock);

        return $zedUiFactoryMock;
    }

    protected function createRequestEventForMethod(
        SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer,
        string $requestMethod
    ): RequestEvent {
        $httpKernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $request = $this->tester->createLoginRequest($requestMethod, $securityCheckAuthContextTransfer);

        return new RequestEvent($httpKernel, $request, $this->getMainRequestType());
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\HttpFoundation\RequestStack
     */
    protected function createRequestStackMock(): RequestStack
    {
        return $this->createMock(RequestStack::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|\Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface
     */
    protected function createSecurityBlockerClientMock(): AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface
    {
        return $this->createMock(AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface::class);
    }

    protected function createSecurityBlockerAgentMerchantPortalLoginEventSubscriber(): SecurityBlockerAgentMerchantPortalLoginEventSubscriber
    {
        $configMock = $this->createConfiguredMock(
            AgentSecurityBlockerMerchantPortalGuiConfig::class,
            [
                'getSecurityBlockerLoginRealm' => AgentSecurityBlockerMerchantPortalGuiCommunicationTester::LOGIN_REALM_USER,
                'getAgentMerchantPortalLoginCheckUrl' => static::LOGIN_CHECK_URL,
                'getSecurityBlockerAgentMerchantPortalEntityType' => static::AGENT_MERCHANT_PORTAL_TYPE,
                'getIpCounterKeyStrategy' => AgentSecurityBlockerMerchantPortalGuiConfig::IP_COUNTER_KEY_STRATEGY_IP,
                'getAgentMerchantPortalLoginUrl' => '/agent-security-merchant-portal-gui/login',
            ],
        );

        return new SecurityBlockerAgentMerchantPortalLoginEventSubscriber(
            $this->requestStackMock,
            $this->securityBlockerClientMock,
            $this->getMockBuilder(MessageBuilder::class)->disableOriginalConstructor()->getMock(),
            $configMock,
            new SecurityCheckAuthContextBuilder($configMock),
            new LoginRejectionResponseBuilder(
                $this->createMock(MessengerFacadeInterface::class),
                $this->createZedUiFactoryMock(),
            ),
        );
    }

    protected function getMainRequestType(): int
    {
        return HttpKernelInterface::MAIN_REQUEST;
    }

    protected function dispatchKernelRequest(RequestEvent $event): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->subscriber);
        $eventDispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    protected function createEventDispatcherWithCaptchaListener(callable $captchaListener): EventDispatcher
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->subscriber);
        $eventDispatcher->addListener(KernelEvents::REQUEST, $captchaListener, static::CAPTCHA_SUBSCRIBER_PRIORITY);

        return $eventDispatcher;
    }

    protected function createFailingCaptchaListener(): callable
    {
        return function (): void {
            $this->fail('The CAPTCHA was verified before the blocked login attempt was rejected.');
        };
    }

    protected function createRejectingCaptchaListener(): callable
    {
        return static function (RequestEvent $event): void {
            $event->setResponse(new RedirectResponse(static::CAPTCHA_REJECTION_URL));
        };
    }
}
