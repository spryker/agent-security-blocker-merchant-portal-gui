<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Plugin;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\CaptchaRequirementRequestTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiDependencyProvider;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\AgentSecurityBlockerMerchantPortalGuiCommunicationFactory;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Plugin\Captcha\SecurityBlockerAgentMerchantPortalCaptchaRequirementPlugin;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface;
use SprykerTest\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiCommunicationTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group AgentSecurityBlockerMerchantPortalGui
 * @group Communication
 * @group Plugin
 * @group SecurityBlockerAgentMerchantPortalCaptchaRequirementPluginTest
 * Add your own group annotations below this line
 */
class SecurityBlockerAgentMerchantPortalCaptchaRequirementPluginTest extends Unit
{
    /**
     * @uses \Spryker\Shared\Captcha\CaptchaConfig::CONTEXT_MERCHANT_PORTAL_USER_LOGIN
     */
    protected const string OTHER_CAPTCHA_CONTEXT = 'merchant_portal_user_login';

    protected const string IP = '10.0.0.1';

    protected const string LOGIN_IDENTIFIER = 'admin@spryker.com';

    protected AgentSecurityBlockerMerchantPortalGuiCommunicationTester $tester;

    public function testCaptchaIsRequiredWhileTheSecurityBlockerRequiresAChallenge(): void
    {
        // Arrange
        $securityBlockerClientMock = $this->createSecurityBlockerClientMock(true);

        // Act
        $isCaptchaRequired = $this->createPlugin($securityBlockerClientMock)->isCaptchaRequired($this->createCaptchaRequirementRequestTransfer());

        // Assert
        $this->assertTrue($isCaptchaRequired);
    }

    public function testCaptchaIsNotRequiredWithoutAChallenge(): void
    {
        // Arrange
        $securityBlockerClientMock = $this->createSecurityBlockerClientMock(false);

        // Act
        $isCaptchaRequired = $this->createPlugin($securityBlockerClientMock)->isCaptchaRequired($this->createCaptchaRequirementRequestTransfer());

        // Assert
        $this->assertFalse($isCaptchaRequired);
    }

    public function testPluginDoesNotServeAnotherContext(): void
    {
        // Arrange
        $securityBlockerClientMock = $this->createMock(AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface::class);
        $securityBlockerClientMock->expects($this->never())->method('isLoginChallengeRequired');

        // Act
        $isCaptchaRequired = $this->createPlugin($securityBlockerClientMock)
            ->isCaptchaRequired($this->createCaptchaRequirementRequestTransfer()->setContext(static::OTHER_CAPTCHA_CONTEXT));

        // Assert
        $this->assertFalse($isCaptchaRequired);
    }

    public function testChallengeIsReadFromThePerIpCounterTheFailedLoginsAreCountedOn(): void
    {
        // Arrange
        $securityBlockerClientMock = $this->createMock(AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface::class);

        // Assert
        $securityBlockerClientMock->expects($this->once())
            ->method('isLoginChallengeRequired')
            ->with($this->callback(fn (SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): bool => $securityCheckAuthContextTransfer->getIp() === static::IP
                && $securityCheckAuthContextTransfer->getLoginIdentifier() === static::LOGIN_IDENTIFIER
                && $securityCheckAuthContextTransfer->getIpCounterKeyStrategy() === AgentSecurityBlockerMerchantPortalGuiConfig::IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME))
            ->willReturn(false);

        // Act
        $this->createPlugin($securityBlockerClientMock, AgentSecurityBlockerMerchantPortalGuiConfig::IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME)
            ->isCaptchaRequired($this->createCaptchaRequirementRequestTransfer());
    }

    protected function createPlugin(
        AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface $securityBlockerClient,
        string $ipCounterKeyStrategy = AgentSecurityBlockerMerchantPortalGuiConfig::IP_COUNTER_KEY_STRATEGY_IP
    ): SecurityBlockerAgentMerchantPortalCaptchaRequirementPlugin {
        $this->tester->setDependency(AgentSecurityBlockerMerchantPortalGuiDependencyProvider::CLIENT_SECURITY_BLOCKER, $securityBlockerClient);

        $agentSecurityBlockerMerchantPortalGuiConfig = new AgentSecurityBlockerMerchantPortalGuiConfig();
        $agentSecurityBlockerMerchantPortalGuiCommunicationFactory = new AgentSecurityBlockerMerchantPortalGuiCommunicationFactory();
        $agentSecurityBlockerMerchantPortalGuiCommunicationFactory->setConfig($this->createConfiguredMock(AgentSecurityBlockerMerchantPortalGuiConfig::class, [
            'getCaptchaContext' => $agentSecurityBlockerMerchantPortalGuiConfig->getCaptchaContext(),
            'getIpCounterKeyStrategy' => $ipCounterKeyStrategy,
            'getSecurityBlockerAgentMerchantPortalEntityType' => $agentSecurityBlockerMerchantPortalGuiConfig->getSecurityBlockerAgentMerchantPortalEntityType(),
            'getSecurityBlockerLoginRealm' => $agentSecurityBlockerMerchantPortalGuiConfig->getSecurityBlockerLoginRealm(),
        ]));

        $securityBlockerBackofficeUserCaptchaRequirementPlugin = new SecurityBlockerAgentMerchantPortalCaptchaRequirementPlugin();
        $securityBlockerBackofficeUserCaptchaRequirementPlugin->setFactory($agentSecurityBlockerMerchantPortalGuiCommunicationFactory);

        return $securityBlockerBackofficeUserCaptchaRequirementPlugin;
    }

    protected function createSecurityBlockerClientMock(bool $isLoginChallengeRequired): AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface
    {
        $securityBlockerClientMock = $this->createMock(AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface::class);
        $securityBlockerClientMock->method('isLoginChallengeRequired')->willReturn($isLoginChallengeRequired);

        return $securityBlockerClientMock;
    }

    protected function createCaptchaRequirementRequestTransfer(): CaptchaRequirementRequestTransfer
    {
        return (new CaptchaRequirementRequestTransfer())
            ->setContext((new AgentSecurityBlockerMerchantPortalGuiConfig())->getCaptchaContext())
            ->setIp(static::IP)
            ->setLoginIdentifier(static::LOGIN_IDENTIFIER);
    }
}
