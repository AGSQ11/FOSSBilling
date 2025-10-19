<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Tax management for clients.
 */

namespace Box\Mod\Tax\Api;

class Client extends \Api_Abstract
{
    /**
     * Get tax rate for current client.
     *
     * @return array
     */
    public function get_client_tax_rate()
    {
        $client = $this->getIdentity();
        $title = null;

        $result = $this->di['api_admin']->tax_get_client_tax_rate(['client_id' => $client->id], $title);

        return $result;
    }

    /**
     * Get client tax exemptions.
     *
     * @return array
     */
    public function get_client_exemptions()
    {
        $client = $this->getIdentity();
        return $this->di['api_admin']->tax_get_client_exemptions(['client_id' => $client->id]);
    }
}