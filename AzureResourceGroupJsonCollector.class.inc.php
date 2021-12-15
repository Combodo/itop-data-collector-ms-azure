<?php

class AzureResourceGroupJsonCollector extends AzureJsonCollector {
	/**
	 * @inheritdoc
	 */
	protected function GetUrl($iSubscription, $sResourceGroupName): string {
		return $this->sResource.'/subscriptions/'.$iSubscription.'/resourcegroups?api-version='.$this->sApiVersion;
	}

	protected function BuildUrl($aParameters): string {
		if (!array_key_exists(self::URI_PARAM_SUBSCRIPTION, $aParameters)) {
			return '';
		} else {
			return $this->sResource.'subscriptions/'.$aParameters[self::URI_PARAM_SUBSCRIPTION].'/resourcegroups?api-version='.$this->sApiVersion;
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function ReportResourceGroups($aData, $iSubscription): void {
		foreach ($aData['value'] as $aResource) {
			$this->oAzureCollectionPlan->AddResourceGroupsToConsider($iSubscription, $aResource['name']);
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function ReportObjects($aData, $sObjectL1, $sObjectL2): void {
		foreach ($aData['value'] as $aObject) {
			$this->oAzureCollectionPlan->AddAzureObjectsToConsider($sObjectL1, $aObject['name'], null);
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function MustProcessBeforeSynchro(): bool {
		return true;
	}

	/**
	 * @inheritdoc
	 */
	protected function DoLookup($aLookupKey, $sDestField): array {
		$sResult = false;
		$sData = '';
		switch ($sDestField) {
			case 'azuresubscription_id':
				if (array_key_exists('primary_key', $aLookupKey) && ($aLookupKey['primary_key'] != '')) {
					$sData = strstr($aLookupKey['primary_key'], 'subscriptions');
					if ($sData !== false) {
						$aData = explode('/', $sData);
						$sData = $aData[1];
						$sResult = true;
					}
				}
				break;

			default:
				break;
		}

		return [$sResult, $sData];
	}

	/**
	 * @inheritdoc
	 */
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex) {
		// Process each line of the CSV
		if (!$this->Lookup($aLineData, array('primary_key'), 'azuresubscription_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
	}

}

