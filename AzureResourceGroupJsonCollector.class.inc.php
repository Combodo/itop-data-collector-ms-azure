<?php

class AzureResourceGroupJsonCollector extends AzureJsonCollector {
	/*
	 *  Tell what URL to use to collect the requested class
	 *
	 * @param $iSubscription
	 *
	 * @return string
	 */
	private function GetUrl($iSubscription): string {
		return $this->sResource.'/subscriptions/'.$iSubscription.'/resourcegroups?api-version='.$this->sApiVersion;
	}

	/**
	 * @inheritdoc
	 */
	protected function RetrieveDataFromAzure(): bool {
		$aSubscriptionsToConsider = AzureCollectionPlan::GetSubscriptionsToConsider();
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
					if (empty($aConcatenatedResults)) {
						$aConcatenatedResults = $aResults;
					} else {
						$aConcatenatedResults['value'] = array_merge($aConcatenatedResults['value'], $aResults);
					}
					Utils::Log(LOG_DEBUG,
						'Data for class '.$this->sAzureClass.' have been retrieved from Azure for Subscription'.$iSubscription.'. Count Total = '.count($aResults['value']));
				}
			} catch (Exception $e) {
				Utils::Log(LOG_WARNING, "Query failed: ".$e->getMessage());

				return false;
			}
		}

		$hJSON = file_put_contents($this->sJsonFile, json_encode($aConcatenatedResults));
		if ($hJSON === false) {
			Utils::Log(LOG_ERR, "Failed to write retrieved data in '$this->sJsonFile' !");

			return false;
		}

		return true;
	}

}

