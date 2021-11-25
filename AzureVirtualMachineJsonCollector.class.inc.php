<?php

class AzureVirtualMachineJsonCollector extends AzureJsonCollector {
	/**
	 *  Tell what URL to use to collect the requested class
	 *
	 * @param $iSubscription
	 * @param $sResourceGroupName
	 *
	 * @return string
	 */
	private function GetUrl($iSubscription, $sResourceGroupName): string {
		return $this->sResource.'/subscriptions/'.$iSubscription.'/resourceGroups/'.$sResourceGroupName.'/providers/Microsoft.Compute/virtualMachines?api-version='.$this->sApiVersion;
	}

	/**
	 * @inheritdoc
	 */
	protected function RetrieveDataFromAzure(): bool {
		$aResourceGroupsToConsider = $this->oAzureCollectionPlan->GetResourceGroupsToConsider();
		$aConcatenatedResults = [];
		foreach ($aResourceGroupsToConsider as $iSubscription => $aParam) {
			foreach ($aParam['ResourceGroup'] as $sResourceGroupName) {
				$sUrl = $this->GetUrl($iSubscription, $sResourceGroupName);
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
						Utils::Log(LOG_DEBUG,
							'Data for class '.$this->sAzureClass.' have been retrieved from Azure for Subscription'.$iSubscription.'. Count Total = '.count($aResults['value']));
					}
				} catch (Exception $e) {
					Utils::Log(LOG_WARNING, "Resource group query failed for subscription '.$iSubscription.': ".$e->getMessage());
				}
			}
		}

		// Store JSON data
		return $this->StoreJsonDataInFile(json_encode($aConcatenatedResults));
	}

}


