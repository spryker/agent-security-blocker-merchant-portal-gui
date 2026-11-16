<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Checker;

use Generated\Shared\Transfer\CaptchaRequirementRequestTransfer;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\SecurityCheckAuthContextBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface;

class LoginAttemptCaptchaRequirementChecker implements LoginAttemptCaptchaRequirementCheckerInterface
{
    public function __construct(
        protected AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface $securityBlockerClient,
        protected SecurityCheckAuthContextBuilderInterface $securityCheckAuthContextBuilder,
        protected AgentSecurityBlockerMerchantPortalGuiConfig $agentSecurityBlockerMerchantPortalGuiConfig
    ) {
    }

    public function isCaptchaRequired(CaptchaRequirementRequestTransfer $captchaRequirementRequestTransfer): bool
    {
        if ($captchaRequirementRequestTransfer->getContextOrFail() !== $this->agentSecurityBlockerMerchantPortalGuiConfig->getCaptchaContext()) {
            return false;
        }

        return $this->securityBlockerClient->isLoginChallengeRequired(
            $this->securityCheckAuthContextBuilder->createSecurityCheckAuthContextTransferForIp(
                $captchaRequirementRequestTransfer->getIp(),
                $captchaRequirementRequestTransfer->getLoginIdentifier(),
            ),
        );
    }
}
