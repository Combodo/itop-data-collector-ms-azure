<?php
require_once(APPROOT.'collectors/msbase/src/MSJsonCollector.class.inc.php');

class IPv4SubnetAzureCollector extends MSJsonCollector
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
        $this->oIPv4SubnetIPConfigMapping = new LookupTable('SELECT IPConfig', array('org_id_friendlyname'));
    }

    public function Prepare(): bool
    {
        // Create mask mapping table
        $this->oIPv4SubnetMaskMapping = new MappingTable('ipv4_subnet_mask_mapping');

        return parent::Prepare();
    }

    /**
	 * @inheritdoc
	 */
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex)
	{
        if (!$this->oIPv4SubnetIPConfigMapping->Lookup($aLineData, array('org_id'), 'ipconfig_id', $iLineIndex)) {
            throw new IgnoredRowException('Unknown IP Config');
        }
	}

	/**
	 * @inheritdoc
	 */
	public function Fetch()
	{
        // The collect has returned a set of IPv4 and IPv6 subnets. We need to extract the IPv4 ones only
        $bEndOfCollect = false;
        $bFoundIPv4Subnet = false;
        while (!$bEndOfCollect && !$bFoundIPv4Subnet) {
            $aData = parent::Fetch();
            if ($aData!==false) {
                // Then process specific data
                $iJsonIdx = $this->iIdx - 1; // Increment is done at the end of parent::Fetch()
                $sIP = strstr($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['addressPrefix'], '/', true);
                if (strpos($sIP, ".")!==false) {
                    // IPv4 format vs IPv6 one is simply tested by presence of '.' in address. Assumption is made that Azure returns coorectly formed IPs.
                    $bFoundIPv4Subnet = true;
                    $aData['ip'] = $sIP;
                    $aData['mask'] = $this->oIPv4SubnetMaskMapping->MapValue(trim(strstr($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['addressPrefix'], '/'), '/'), '');
                }

            } else {
                $bEndOfCollect = true;
            }
        }

		return $aData;
	}
}

