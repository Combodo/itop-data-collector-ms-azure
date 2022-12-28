<?php
require_once(APPROOT.'collectors/msbase/src/MSJsonCollector.class.inc.php');

class IPv6SubnetAzureCollector extends MSJsonCollector
{
	// Required parameters to build URL
	protected static $aURIParameters = [
		1 => self::URI_PARAM_SUBSCRIPTION,
		2 => self::URI_PARAM_RESOURCEGROUP,
		3 => self::URI_PARAM_VNET,
	];

    /**
     * @inheritdoc
     */
    public function CheckToLaunch($aOrchestratedCollectors): bool
    {
        if (parent::CheckToLaunch($aOrchestratedCollectors)) {
            if ($this->oMSCollectionPlan->IsTeemIpInstalled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function AttributeIsOptional($sAttCode): bool
    {
        if ($this->oMSCollectionPlan->IsTeemIpIpDiscoveryinstalled()) {
            // TODO
        } else {
            // TODO
        }

        return parent::AttributeIsOptional($sAttCode);
    }

    /**
	 * @inheritdoc
	 */
	protected function BuildUrl($aParameters): string
	{
		if (!array_key_exists(self::URI_PARAM_SUBSCRIPTION, $aParameters) || !array_key_exists(self::URI_PARAM_RESOURCEGROUP,
				$aParameters) || !array_key_exists(self::URI_PARAM_VNET, $aParameters)) {
			return '';
		} else {
			$sUrl = $this->sResource.'subscriptions/'.$aParameters[self::URI_PARAM_SUBSCRIPTION];
			$sUrl .= '/resourceGroups/'.$aParameters[self::URI_PARAM_RESOURCEGROUP];
			$sUrl .= '/providers/Microsoft.Network/virtualNetworks/'.$aParameters[self::URI_PARAM_VNET];
			$sUrl .= '/subnets?api-version='.$this->sApiVersion;

			return $sUrl;
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function MustProcessBeforeSynchro(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
    protected function InitProcessBeforeSynchro(): void
    {
        // Create IPConfig mapping table
        $this->oIPv6SubnetIPConfigMapping = new LookupTable('SELECT IPConfig', array('org_id_friendlyname'));
    }

    /**
	 * @inheritdoc
	 */
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex)
	{
        if (!$this->oIPv6SubnetIPConfigMapping->Lookup($aLineData, array('org_id'), 'ipconfig_id', $iLineIndex)) {
            throw new IgnoredRowException('Unknown IP Config');
        }
	}

	/**
	 * @inheritdoc
	 */
	public function Fetch()
	{
        // The collect has returned a set of IPv4 and IPv6 subnets. We need to extract the IPv6 ones only
        $bEndOfCollect = false;
        $bFoundIPv6Subnet = false;
        while (!$bEndOfCollect && !$bFoundIPv6Subnet) {
            $aData = parent::Fetch();
            if ($aData !== false) {
                // Then process specific data
                $iJsonIdx = $this->iIdx - 1; // Increment is done at the end of parent::Fetch()
                $sIP = strstr($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['addressPrefix'], '/', true);
                if (strpos($sIP, ":") !== false) {
                    // IPv6 format vs Ipv4 one is simply tested by presence of ':' in address. Assumption is made that Azure returns coorectly formed IPs.
                    $bFoundIPv6Subnet = true;
                    $aData['ip'] = $sIP;
                    $aData['mask'] = trim(strstr($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['addressPrefix'], '/'), '/');
                }

            } else {
                $bEndOfCollect = true;
            }
        }

		return $aData;
	}
}

