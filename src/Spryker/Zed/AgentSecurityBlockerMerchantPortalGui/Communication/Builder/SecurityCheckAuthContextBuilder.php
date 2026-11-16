<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Symfony\Component\HttpFoundation\Request;

class SecurityCheckAuthContextBuilder implements SecurityCheckAuthContextBuilderInterface
{
    public function __construct(
        protected AgentSecurityBlockerMerchantPortalGuiConfig $agentSecurityBlockerMerchantPortalGuiConfig
    ) {
    }

    public function createSecurityCheckAuthContextTransfer(Request $request, ?string $loginIdentifier): SecurityCheckAuthContextTransfer
    {
        return $this->createSecurityCheckAuthContextTransferForIp($request->getClientIp(), $loginIdentifier);
    }

    public function createSecurityCheckAuthContextTransferForIp(?string $ip, ?string $loginIdentifier): SecurityCheckAuthContextTransfer
    {
        return (new SecurityCheckAuthContextTransfer())
            ->setType($this->agentSecurityBlockerMerchantPortalGuiConfig->getSecurityBlockerAgentMerchantPortalEntityType())
            ->setIp($ip)
            ->setRealm($this->agentSecurityBlockerMerchantPortalGuiConfig->getSecurityBlockerLoginRealm())
            ->setLoginIdentifier($loginIdentifier)
            ->setIpCounterKeyStrategy($this->agentSecurityBlockerMerchantPortalGuiConfig->getIpCounterKeyStrategy());
    }
}
