<?php
require_once(APPROOT.'collectors/MSCollectionPlan.class.inc.php');

class AzureCollectionPlan extends MSCollectionPlan
{
	public function __construct()
	{
		parent::__construct();

		// Fetch from iTop the list of subscriptions to discover
		Utils::Log(LOG_INFO, '---------- Fetch from iTop the list of Subscriptions to discover ----------');
		$oRestClient = new RestClient();
		try {
			$aResult = $oRestClient->Get('AzureSubscription', 'SELECT AzureSubscription WHERE discover_objects = \'yes\'');
			if ($aResult['code'] != 0) {
				Utils::Log(LOG_ERR, "{$aResult['message']} ({$aResult['code']})");
			} else {
				if (empty($aResult['objects'])) {
					// No object found
					Utils::Log(LOG_INFO, "There is no Azure subscription stored in iTop for which objects need to be discovered.");
				} else {
					foreach ($aResult['objects'] as $sKey => $aData) {
						$aAzureSubscriptionAttributes = $aData['fields'];
						$iSubscriptionId = $aAzureSubscriptionAttributes['subscriptionid'];
						$this->aMSObjectsToConsider[$iSubscriptionId] = [];

						Utils::Log(LOG_INFO, 'Name: '.$aAzureSubscriptionAttributes['name'].' - ID: '.$iSubscriptionId);
					}
				}
			}
		} catch (Exception $e) {
			$sMessage = 'Cannot fetch subscriptions from iTop: '.$e->getMessage();
			if (is_a($e, "IOException")) {
				Utils::Log(LOG_ERR, $sMessage);
				throw $e;
			}
		}

		// Fetch from iTop the list of Resource Groups that belong to subscriptions to discover
		if (!empty($this->aMSObjectsToConsider)) {
			Utils::Log(LOG_INFO, '---------- Fetch from iTop the list of Resource groups ----------');
			$bFirstEntry = true;
			$sSubscriptionList = '';
			foreach ($this->aMSObjectsToConsider as $sObjectL1 => $aObjectL1) {
				$sSubscriptionList .= ($bFirstEntry) ? "'".$sObjectL1."'" : ",'".$sObjectL1."'";
				$bFirstEntry = false;
			}
			$oRestClient = new RestClient();
			try {
				$aResult = $oRestClient->Get('AzureResourceGroup',
					'SELECT AzureResourceGroup AS rg JOIN AzureSubscription AS s ON rg.azuresubscription_id =  s.id WHERE s.subscriptionid IN ('.$sSubscriptionList.')');
				if ($aResult['code'] != 0) {
					Utils::Log(LOG_ERR, "{$aResult['message']} ({$aResult['code']})");
				} else {
					if (empty($aResult['objects'])) {
						// No object found
						Utils::Log(LOG_INFO,
							"There is no Azure resource groups already stored in iTop within the subscriptions to discover.");
					} else {
						foreach ($aResult['objects'] as $sKey => $aData) {
							$aAzureResourceGroupAttributes = $aData['fields'];
							$sResourceGroupName = $aAzureResourceGroupAttributes['name'];
							$this->AddMSObjectsToConsider($aAzureResourceGroupAttributes['azuresubscription_subscriptionid'],
								$sResourceGroupName, null);

							Utils::Log(LOG_INFO,
								'Subscription ID: '.$aAzureResourceGroupAttributes['azuresubscription_name'].' - Name: '.$sResourceGroupName);
						}
					}
				}
			} catch (Exception $e) {
				$sMessage = 'Cannot fetch subscriptions from iTop: '.$e->getMessage();
				if (is_a($e, "IOException")) {
					Utils::Log(LOG_ERR, $sMessage);
					throw $e;
				}
			}
		}

	}

	/**
	 * Tell if a collector need to be orchestrated or not
	 *
	 * @param $sCollectorClass
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function CollectorToBeLaunched($sCollectorClass): bool
	{
		$aParamsSourceJson = Utils::GetConfigurationValue(strtolower($sCollectorClass), array());
		if ($aParamsSourceJson === null) {
			return false;
		} elseif (isset($aParamsSourceJson['enable']) && ($aParamsSourceJson['enable'] == 'yes')) {
			if (!$this->IsSubscriptionToConsider() && ($sCollectorClass != 'AzureSubscriptionJsonCollector')) {
				Utils::Log(LOG_INFO, $sCollectorClass.' will not be launched as no subscription should be discovered');

				return false;
			}
			if ($sCollectorClass::NeedsResourceGroupsForCollector()) {
				if (!$this->IsResourceGroupToConsider()) {
					$aParamsResourceGroupJson = Utils::GetConfigurationValue(strtolower('AzureResourceGroupJsonCollector'), array());
					if (!isset($aParamsResourceGroupJson['enable']) || ($aParamsResourceGroupJson['enable'] != 'yes')) {
						Utils::Log(LOG_INFO, $sCollectorClass.' will not be launched as no resource group should be discovered');

						return false;
					}
				}
			}

			Utils::Log(LOG_INFO, $sCollectorClass.' will be launched !');

			return true;
		}

		return false;
	}
}
