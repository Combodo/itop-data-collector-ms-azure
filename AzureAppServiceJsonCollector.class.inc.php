<?php

class AzureAppServiceJsonCollector extends AzureJsonCollector {
	/**
	 *  Tell what URL to use to collect the requested class
	 *
	 * @param $iSubscription
	 *
	 * @return string
	 */
	private function GetUrl($iSubscription): string {
		return $this->sResource.'/subscriptions/'.$iSubscription.'/providers/Microsoft.Web/sites?api-version='.$this->sApiVersion;
	}

	/**
	 * @inheritdoc
	 */
	protected function RetrieveDataFromAzure(): bool {
		$aSubscriptionsToConsider = $this->oAzureCollectionPlan->GetSubscriptionsToConsider();
		$aConcatenatedResults = [];
		foreach ($aSubscriptionsToConsider as $iSubscription) {
			$sUrl = $this->GetUrl($iSubscription);
			$aEmpty = [];
			$aOptionnalHeaders = [
				'Content-type: application/json',
				'Authorization: Bearer '.$this->sBearerToken,
			];
			$sOptionnalHeaders = implode("\n", $aOptionnalHeaders);
			$aCurlOptions = array(CURLOPT_POSTFIELDS => "");
			try {
				$sResponse = utils::DoPostRequest($sUrl, $aEmpty, $sOptionnalHeaders, $aEmpty, $aCurlOptions);
				$aResults = json_decode($sResponse, true);
				if (isset($aResults['error'])) {
					Utils::Log(LOG_ERR,
						"Data collection for ".$this->sAzureClass." failed: 
					                Error code: ".$aResults['error']['code']."
					                Message: ".$aResults['error']['message']);

					return false;
				} else {
					if (!empty($aResults['value'])) {
						if (empty($aConcatenatedResults)) {
							$aConcatenatedResults = $aResults;
						} else {
							$aConcatenatedResults['value'] = array_merge($aConcatenatedResults['value'], $aResults);
						}
					}

					// Report list of discovered resource group to the collection plan
					foreach ($aResults['value'] as $aResource) {
						$this->oAzureCollectionPlan->AddResourceGroupsToConsider($iSubscription, $aResource['name']);
					}
					Utils::Log(LOG_DEBUG,
						'Data for class '.$this->sAzureClass.' have been retrieved from Azure for Subscription'.$iSubscription.'. Count Total = '.count($aResults['value']));
				}
			} catch (Exception $e) {
				Utils::Log(LOG_WARNING, "Query failed: ".$e->getMessage());

				return false;
			}
		}

		// Store JSON data
		return $this->StoreJsonDataInFile(json_encode($aConcatenatedResults));
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
			case 'azureresourcegroup_id':
				if (array_key_exists('primary_key', $aLookupKey) && ($aLookupKey['primary_key'] != '')) {
					$sData = strstr($aLookupKey['primary_key'], 'resourceGroups');
					if ($sData !== false) {
						$aData = explode('/', $sData);
						$sData = $aData[1];
						$sResult = true;
					}
				}
				break;

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
		if (!$this->Lookup($aLineData, array('primary_key'), 'azureresourcegroup_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
		if (!$this->Lookup($aLineData, array('primary_key'), 'azuresubscription_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
	}

}

