<?php

class AzureLocationJsonCollector extends AzureJsonCollector {
	/*
	 *  Tell what URL to use to collect the requested class
	 *
	 * @param $iSubscription
	 *
	 * @return string
	 */
	private function GetUrl($iSubscription): string {
		return $this->sResource.'/subscriptions/'.$iSubscription.'/providers/Microsoft.DocumentDB/locations?api-version='.$this->sApiVersion;
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

}

